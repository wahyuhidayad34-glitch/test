<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use App\Services\ApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(protected ApprovalService $approvalService)
    {
    }

    /** GET /api/dashboard/metrics  - angka dibatasi sesuai PR yang boleh dilihat user */
    public function metrics(Request $request): JsonResponse
    {
        $user = $request->user();
        $base = PurchaseRequest::visibleTo($user);
        $approvedStatuses = [PurchaseRequest::STATUS_APPROVED, PurchaseRequest::STATUS_CONVERTED];

        $metrics = [
            'total_pr_submitted' => (clone $base)->where('status', '!=', PurchaseRequest::STATUS_DRAFT)->count(),
            'pending_approval' => (clone $base)->whereIn('status', PurchaseRequest::IN_APPROVAL)->count(),
            'approved_pr' => (clone $base)->whereIn('status', $approvedStatuses)->count(),
            'rejected_pr' => (clone $base)->where('status', PurchaseRequest::STATUS_REJECTED)->count(),
            'total_amount' => (clone $base)->whereIn('status', $approvedStatuses)->sum('total_amount'),
            // berlaku untuk manager, finance, dan director
            'my_pending_approvals' => $this->approvalService->getPendingApprovalsForUser($user)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $metrics,
        ]);
    }

    /** GET /api/dashboard/pending-tasks */
    public function pendingTasks(Request $request): JsonResponse
    {
        $tasks = $this->approvalService->getPendingApprovalsForUser($request->user())
            ->map(fn ($approval) => [
                'type' => 'approval_needed',
                'title' => 'Approval needed: ' . ($approval->purchaseRequest?->pr_number ?? '-'),
                'document_id' => $approval->document_id,
                'level' => $approval->approval_level,
                'amount' => $approval->purchaseRequest?->total_amount,
                'created_at' => $approval->created_at,
            ])
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => $tasks,
            'count' => count($tasks),
        ]);
    }
}
