<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use App\Services\ApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function __construct(protected ApprovalService $approvalService)
    {
    }

    /** GET /api/approvals/pending  - hanya approval yang memang sedang GILIRAN user */
    public function pending(Request $request): JsonResponse
    {
        $approvals = $this->approvalService->getPendingApprovalsForUser($request->user());

        $data = $approvals->map(fn ($approval) => [
            'id' => $approval->id,
            'document_type' => $approval->document_type,
            'document_id' => $approval->document_id,
            'approval_level' => $approval->approval_level,
            'level_name' => ApprovalService::levelName($approval->approval_level),
            'document_number' => $approval->purchaseRequest?->pr_number,
            'amount' => $approval->purchaseRequest?->total_amount,
            'requester' => $approval->purchaseRequest?->requester?->name,
            'created_at' => $approval->created_at,
        ])->values();

        return response()->json([
            'success' => true,
            'data' => $data,
            'count' => $data->count(),
        ]);
    }

    /** GET /api/approvals/history/{type}/{id} */
    public function history(Request $request, string $type, int $id): JsonResponse
    {
        if ($type !== 'pr') {
            throw new BusinessRuleException('Tipe dokumen ini belum didukung');
        }

        $pr = PurchaseRequest::findOrFail($id);
        if (!$pr->canBeViewedBy($request->user())) {
            throw new BusinessRuleException('Anda tidak berhak mengakses dokumen ini', 403);
        }

        $data = $this->approvalService->getApprovalHistory($type, $id)->map(fn ($approval) => [
            'level' => $approval->approval_level,
            'level_name' => ApprovalService::levelName($approval->approval_level),
            'approver' => $approval->approver?->name,
            'status' => $approval->status,
            'comments' => $approval->comments,
            'approved_at' => $approval->approved_at,
        ])->values();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
