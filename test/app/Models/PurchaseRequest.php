<?php

namespace App\Models;

use App\Models\Concerns\GeneratesDocumentNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PurchaseRequest extends Model
{
    use GeneratesDocumentNumber;

    /*
     * Alur status:
     *   draft -> submitted -> approved_manager -> approved_finance -> approved -> converted_to_po
     *                  \___________ rejected (bisa diedit lalu di-submit ulang) ___________/
     *
     *   < 50 jt      : Manager                      -> approved
     *   50 - <500 jt : Manager, Finance             -> approved
     *   >= 500 jt    : Manager, Finance, Director   -> approved
     */
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED_MANAGER = 'approved_manager';
    public const STATUS_APPROVED_FINANCE = 'approved_finance';
    public const STATUS_APPROVED = 'approved';           // disetujui penuh
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CONVERTED = 'converted_to_po';

    /** Status ketika PR sedang menunggu keputusan approver */
    public const IN_APPROVAL = [
        self::STATUS_SUBMITTED,
        self::STATUS_APPROVED_MANAGER,
        self::STATUS_APPROVED_FINANCE,
    ];

    protected $table = 'purchase_requests';

    protected $fillable = [
        'pr_number',
        'requester_id',
        'department',
        'pr_date',
        'required_date',
        'status',
        'total_amount',
        'description',
        'notes',
        'rejection_reason',
    ];

    protected $casts = [
        'pr_date' => 'date',
        'required_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    // Boot - Auto generate PR Number
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->pr_number) {
                $model->pr_number = static::nextDocumentNumber('PR', 'pr_number');
            }
        });
    }

    // Relationships
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class, 'pr_id');
    }

    public function purchaseOrder(): HasOne
    {
        return $this->hasOne(PurchaseOrder::class, 'pr_id');
    }

    public function approvalWorkflows(): HasMany
    {
        return $this->hasMany(ApprovalWorkflow::class, 'document_id')
                    ->where('document_type', 'pr');
    }

    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByRequester($query, $userId)
    {
        return $query->where('requester_id', $userId);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', self::IN_APPROVAL);
    }

    /**
     * Batasi PR yang boleh dilihat user:
     *  - admin              : semua
     *  - finance / director : semua yang sudah di-submit + miliknya sendiri
     *  - manager            : miliknya, PR departemennya (non-draft), PR yang ia approve
     *  - requester          : hanya miliknya
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('requester_id', $user->id);

            if ($user->isFinance() || $user->isDirector()) {
                $q->orWhere('status', '!=', self::STATUS_DRAFT);
                return;
            }

            $q->orWhereHas('approvalWorkflows', fn ($w) => $w->where('approver_id', $user->id));

            if ($user->isManager() && $user->department) {
                $q->orWhere(function (Builder $d) use ($user) {
                    $d->where('department', $user->department)
                      ->where('status', '!=', self::STATUS_DRAFT);
                });
            }
        });
    }

    // Methods
    public function isOwnedBy(User $user): bool
    {
        return (int) $this->requester_id === (int) $user->id;
    }

    public function canBeViewedBy(User $user): bool
    {
        return static::query()->visibleTo($user)->whereKey($this->getKey())->exists();
    }

    /** Boleh diedit / dihapus? (draft, atau ditolak untuk diperbaiki) */
    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REJECTED], true);
    }

    public function canSubmit(): bool
    {
        return $this->isEditable() && $this->items()->count() > 0;
    }

    public function canApprove(): bool
    {
        return in_array($this->status, self::IN_APPROVAL, true);
    }

    public function calculateTotal()
    {
        return $this->items()->sum('total_price');
    }
}
