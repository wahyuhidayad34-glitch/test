<?php

namespace App\Services;

use App\Events\ApprovalNeeded;
use App\Events\PRApproved;
use App\Events\PRRejected;
use App\Exceptions\BusinessRuleException;
use App\Models\ApprovalWorkflow;
use App\Models\AuditLog;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * ApprovalService - workflow approval multi-level BERURUTAN
 *
 *   < 50 jt        : Manager
 *   50 jt - <500jt : Manager -> Finance
 *   >= 500 jt      : Manager -> Finance -> Director
 *
 * Level N hanya bisa bertindak setelah semua level di bawahnya "approved".
 */
class ApprovalService
{
    public const MANAGER_LEVEL = 1;
    public const FINANCE_LEVEL = 2;
    public const DIRECTOR_LEVEL = 3;

    public const FINANCE_THRESHOLD = 50_000_000;    // >= 50 jt butuh Finance
    public const DIRECTOR_THRESHOLD = 500_000_000;  // >= 500 jt butuh Director

    // ------------------------------------------------------------------
    // SUBMIT
    // ------------------------------------------------------------------
    public function submitForApproval(PurchaseRequest $pr, User $requester): bool
    {
        DB::transaction(function () use ($pr, $requester) {
            $pr = PurchaseRequest::whereKey($pr->id)->lockForUpdate()->firstOrFail();

            if (!$pr->canSubmit()) {
                throw new BusinessRuleException(
                    'PR tidak valid untuk di-submit (harus berstatus draft/rejected dan punya minimal 1 item)'
                );
            }

            $total = (float) $pr->calculateTotal();
            if ($total <= 0) {
                throw new BusinessRuleException('Total PR harus lebih dari 0');
            }

            // Tentukan approver SEMUA level dulu; gagal lebih awal bila ada yang tidak ditemukan
            $approvers = [];
            foreach ($this->getRequiredApprovalLevels($total) as $level) {
                $approver = $this->getApproverForLevel($pr, $level);
                if (!$approver) {
                    throw new BusinessRuleException("Tidak bisa menemukan approver untuk level {$level}");
                }
                $approvers[$level] = $approver;
            }

            $before = $pr->toArray();
            $action = $pr->status === PurchaseRequest::STATUS_REJECTED ? 'resubmitted' : 'submitted';

            // Siklus approval baru: hapus workflow lama (riwayat tetap ada di audit_logs)
            ApprovalWorkflow::where('document_type', 'pr')->where('document_id', $pr->id)->delete();

            $pr->update([
                'status' => PurchaseRequest::STATUS_SUBMITTED,
                'pr_date' => now()->toDateString(),
                'total_amount' => $total,
                'rejection_reason' => null,
            ]);

            foreach ($approvers as $level => $approver) {
                ApprovalWorkflow::create([
                    'document_type' => 'pr',
                    'document_id' => $pr->id,
                    'approval_level' => $level,
                    'approver_id' => $approver->id,
                    'status' => 'pending',
                ]);
            }

            $this->logAudit($pr, $action, $before, $pr->fresh()->toArray());

            // Hanya approver level pertama yang diberi tahu; sisanya menyusul berurutan
            $firstLevel = min(array_keys($approvers));
            DB::afterCommit(fn () => event(new ApprovalNeeded($pr, $approvers[$firstLevel], $firstLevel)));
        });

        return true;
    }

    // ------------------------------------------------------------------
    // APPROVE
    // ------------------------------------------------------------------
    public function approve(PurchaseRequest $pr, User $approver, ?string $comments = null, ?int $level = null): bool
    {
        DB::transaction(function () use ($pr, $approver, $comments, $level) {
            $pr = PurchaseRequest::whereKey($pr->id)->lockForUpdate()->firstOrFail();
            $approval = $this->resolveActionableApproval($pr, $approver, $level);

            $before = $pr->toArray();
            $approval->approve($comments);

            $next = ApprovalWorkflow::where('document_type', 'pr')
                ->where('document_id', $pr->id)
                ->where('status', 'pending')
                ->orderBy('approval_level')
                ->first();

            if ($next) {
                // Masih ada level berikutnya
                $pr->update(['status' => $this->statusAfterLevel($approval->approval_level)]);
                $this->logAudit($pr, 'approved_level_' . $approval->approval_level, $before, $pr->toArray());

                $nextApprover = $next->approver;
                DB::afterCommit(fn () => event(new ApprovalNeeded($pr, $nextApprover, $next->approval_level)));
            } else {
                // Semua level selesai
                $pr->update(['status' => PurchaseRequest::STATUS_APPROVED]);
                $this->logAudit($pr, 'approved', $before, $pr->toArray());

                DB::afterCommit(fn () => event(new PRApproved($pr)));
            }
        });

        return true;
    }

    // ------------------------------------------------------------------
    // REJECT
    // ------------------------------------------------------------------
    public function reject(PurchaseRequest $pr, User $approver, string $reason, ?int $level = null): bool
    {
        DB::transaction(function () use ($pr, $approver, $reason, $level) {
            $pr = PurchaseRequest::whereKey($pr->id)->lockForUpdate()->firstOrFail();
            $approval = $this->resolveActionableApproval($pr, $approver, $level);

            $before = $pr->toArray();
            $approval->reject($reason);

            // Level lain yang masih pending ikut dibatalkan
            ApprovalWorkflow::where('document_type', 'pr')
                ->where('document_id', $pr->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'rejected',
                    'comments' => 'Dibatalkan otomatis karena ditolak di level ' . $approval->approval_level,
                ]);

            // Status 'rejected': requester boleh memperbaiki lalu submit ulang
            $pr->update([
                'status' => PurchaseRequest::STATUS_REJECTED,
                'rejection_reason' => $reason,
            ]);

            $this->logAudit($pr, 'rejected', $before, ['status' => 'rejected', 'reason' => $reason, 'level' => $approval->approval_level]);

            DB::afterCommit(fn () => event(new PRRejected($pr, $reason)));
        });

        return true;
    }

    // ------------------------------------------------------------------
    // QUERY HELPERS
    // ------------------------------------------------------------------

    /** Approval PENDING yang benar-benar boleh diproses sekarang (semua level di bawahnya sudah approved). */
    private function actionableQuery()
    {
        return ApprovalWorkflow::query()
            ->where('approval_workflows.document_type', 'pr')
            ->where('approval_workflows.status', 'pending')
            ->whereNotExists(function (QueryBuilder $q) {
                $q->selectRaw('1')
                  ->from('approval_workflows as lower_level')
                  ->whereColumn('lower_level.document_type', 'approval_workflows.document_type')
                  ->whereColumn('lower_level.document_id', 'approval_workflows.document_id')
                  ->whereColumn('lower_level.approval_level', '<', 'approval_workflows.approval_level')
                  ->where('lower_level.status', '!=', 'approved');
            });
    }

    public function getActionableApproval(User $user, PurchaseRequest $pr): ?ApprovalWorkflow
    {
        return $this->actionableQuery()
            ->where('approval_workflows.document_id', $pr->id)
            ->where('approval_workflows.approver_id', $user->id)
            ->first();
    }

    public function canApprove(User $user, string $documentType, int $documentId): bool
    {
        if ($documentType !== 'pr') {
            return false;
        }

        return $this->actionableQuery()
            ->where('approval_workflows.document_id', $documentId)
            ->where('approval_workflows.approver_id', $user->id)
            ->exists();
    }

    public function getPendingApprovalsForUser(User $user): Collection
    {
        return $this->actionableQuery()
            ->where('approval_workflows.approver_id', $user->id)
            ->with(['purchaseRequest.requester'])
            ->orderByDesc('approval_workflows.created_at')
            ->get();
    }

    public function getApprovalHistory(string $documentType, int $documentId): Collection
    {
        return ApprovalWorkflow::where('document_type', $documentType)
            ->where('document_id', $documentId)
            ->with('approver')
            ->orderBy('approval_level')
            ->get();
    }

    public function getApprovalStatusSummary(PurchaseRequest $pr): array
    {
        $approvals = $this->getApprovalHistory('pr', $pr->id);

        $summary = [
            'total_levels' => $approvals->count(),
            'approved_levels' => $approvals->where('status', 'approved')->count(),
            'pending_levels' => $approvals->where('status', 'pending')->count(),
            'rejected_levels' => $approvals->where('status', 'rejected')->count(),
            'details' => [],
        ];

        foreach ($approvals as $approval) {
            $summary['details'][] = [
                'level' => $approval->approval_level,
                'level_name' => self::levelName($approval->approval_level),
                'approver' => $approval->approver?->name,
                'status' => $approval->status,
                'comments' => $approval->comments,
                'approved_at' => $approval->approved_at,
            ];
        }

        return $summary;
    }

    public static function levelName(int $level): string
    {
        return match ($level) {
            self::MANAGER_LEVEL => 'Manager Approval',
            self::FINANCE_LEVEL => 'Finance Approval',
            self::DIRECTOR_LEVEL => 'Director Approval',
            default => 'Unknown',
        };
    }

    // ------------------------------------------------------------------
    // INTERNAL
    // ------------------------------------------------------------------

    /**
     * Validasi semua aturan sebelum approve/reject dan kembalikan baris approval
     * (dikunci) yang akan diproses.
     */
    private function resolveActionableApproval(PurchaseRequest $pr, User $user, ?int $level): ApprovalWorkflow
    {
        if (!$pr->canApprove()) {
            throw new BusinessRuleException('PR ini tidak sedang menunggu approval');
        }

        if ($pr->isOwnedBy($user)) {
            throw new BusinessRuleException('Requester tidak boleh memproses PR miliknya sendiri', 403);
        }

        $approval = $this->getActionableApproval($user, $pr);
        if (!$approval) {
            throw new BusinessRuleException(
                'Anda bukan approver aktif untuk PR ini (bukan giliran Anda, atau sudah diproses)',
                403
            );
        }

        if ($level !== null && $level !== $approval->approval_level) {
            throw new BusinessRuleException(
                "Level tidak sesuai. Giliran Anda adalah level {$approval->approval_level}"
            );
        }

        // Kunci baris & cek ulang status (cegah double-click / request paralel)
        $approval = ApprovalWorkflow::whereKey($approval->id)->lockForUpdate()->firstOrFail();
        if ($approval->status !== 'pending') {
            throw new BusinessRuleException('Approval sudah diproses');
        }

        return $approval;
    }

    private function getRequiredApprovalLevels(float $amount): array
    {
        if ($amount < self::FINANCE_THRESHOLD) {
            return [self::MANAGER_LEVEL];
        }
        if ($amount < self::DIRECTOR_THRESHOLD) {
            return [self::MANAGER_LEVEL, self::FINANCE_LEVEL];
        }
        return [self::MANAGER_LEVEL, self::FINANCE_LEVEL, self::DIRECTOR_LEVEL];
    }

    /** Approver level tertentu; requester tidak pernah menjadi approver PR-nya sendiri. */
    private function getApproverForLevel(PurchaseRequest $pr, int $level): ?User
    {
        $requester = $pr->requester;

        $byRole = fn (string $role) => User::active()
            ->byRole($role)
            ->where('id', '!=', $requester->id)
            ->orderBy('id');

        return match ($level) {
            self::MANAGER_LEVEL => $this->findManager($requester, $byRole),
            self::FINANCE_LEVEL => $byRole('finance')->first(),
            self::DIRECTOR_LEVEL => $byRole('director')->first(),
            default => null,
        };
    }

    private function findManager(User $requester, callable $byRole): ?User
    {
        $manager = $requester->manager;
        if ($manager && $manager->is_active && $manager->id !== $requester->id) {
            return $manager;
        }

        return $byRole('manager')->where('department', $requester->department)->first();
    }

    /** Status PR setelah suatu level disetujui tetapi masih ada level berikutnya. */
    private function statusAfterLevel(int $level): string
    {
        return match ($level) {
            self::MANAGER_LEVEL => PurchaseRequest::STATUS_APPROVED_MANAGER,
            self::FINANCE_LEVEL => PurchaseRequest::STATUS_APPROVED_FINANCE,
            default => PurchaseRequest::STATUS_APPROVED,
        };
    }

    private function logAudit($document, string $action, ?array $oldValues, ?array $newValues): void
    {
        AuditLog::create([
            'document_type' => 'pr',
            'document_id' => $document->id,
            'action' => $action,
            'user_id' => auth()->id(),
            'old_values' => $oldValues,   // array; model meng-encode ke JSON (tidak lagi double-encode)
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
