<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Services\ApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Catatan desain:
 *  - Tidak ada try/catch (Exception) lagi. Error validasi => 422, BusinessRuleException => 422/403,
 *    data tidak ada => 404 (ditangani di bootstrap/app.php). Error tak terduga => 500 generik
 *    (detail hanya muncul bila APP_DEBUG=true dan tercatat di storage/logs).
 *  - Autentikasi sudah ditangani group route `auth:sanctum` (tanpa $this->middleware()).
 */
class PurchaseRequestController extends Controller
{
    public function __construct(protected ApprovalService $approvalService)
    {
    }

    /** GET /api/purchase-requests */
    public function index(Request $request): JsonResponse
    {
        $query = PurchaseRequest::visibleTo($request->user())
            ->with(['requester', 'items', 'approvalWorkflows']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('requester_id')) {
            $query->where('requester_id', $request->input('requester_id'));
        }
        if ($request->filled('from_date')) {
            $query->whereDate('pr_date', '>=', $request->input('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->whereDate('pr_date', '<=', $request->input('to_date'));
        }
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('pr_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // per_page dibatasi 1..100
        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $prs = $query->latest('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $prs->items(),
            'pagination' => [
                'total' => $prs->total(),
                'per_page' => $prs->perPage(),
                'current_page' => $prs->currentPage(),
                'last_page' => $prs->lastPage(),
            ],
        ]);
    }

    /** GET /api/purchase-requests/{id} */
    public function show(Request $request, $id): JsonResponse
    {
        $pr = PurchaseRequest::with(['requester', 'items', 'approvalWorkflows.approver', 'purchaseOrder'])
            ->findOrFail($id);

        $this->authorizeView($request, $pr);

        return response()->json([
            'success' => true,
            'data' => $pr,
            'approval_summary' => $this->approvalService->getApprovalStatusSummary($pr),
        ]);
    }

    /** POST /api/purchase-requests */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'department' => 'required|string|max:100',
            'required_date' => 'required|date',
            'description' => 'required|string',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_name' => 'required|string|max:255',
            'items.*.description' => 'nullable|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit' => 'required|string|max:50',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.specification' => 'nullable|string',
        ]);

        $pr = DB::transaction(function () use ($request, $validated) {
            $pr = PurchaseRequest::create([
                'requester_id' => $request->user()->id,
                'department' => $validated['department'],
                'pr_date' => now()->toDateString(),
                'required_date' => $validated['required_date'],
                'description' => $validated['description'],
                'notes' => $validated['notes'] ?? null,
                'status' => PurchaseRequest::STATUS_DRAFT,
            ]);

            $this->syncItems($pr, $validated['items']);

            return $pr;
        });

        return response()->json([
            'success' => true,
            'message' => 'Purchase request created successfully',
            'data' => $pr->fresh('items'),
        ], 201);
    }

    /** PUT /api/purchase-requests/{id} */
    public function update(Request $request, $id): JsonResponse
    {
        $pr = PurchaseRequest::findOrFail($id);
        $user = $request->user();

        if (!$pr->isOwnedBy($user) && !$user->isAdmin()) {
            throw new BusinessRuleException('Unauthorized', 403);
        }
        if (!$pr->isEditable()) {
            throw new BusinessRuleException('Hanya PR berstatus draft atau rejected yang bisa diedit');
        }

        // FIX: validasi di luar try/catch (dulu berubah jadi 500) dan semua field item divalidasi
        $validated = $request->validate([
            'department' => 'sometimes|required|string|max:100',
            'required_date' => 'sometimes|required|date',
            'description' => 'sometimes|required|string',
            'notes' => 'nullable|string',
            'items' => 'sometimes|required|array|min:1',
            'items.*.item_name' => 'required|string|max:255',
            'items.*.description' => 'nullable|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit' => 'required|string|max:50',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.specification' => 'nullable|string',
        ]);

        DB::transaction(function () use ($pr, $validated) {
            $pr->fill(Arr::only($validated, ['department', 'required_date', 'description', 'notes']));

            // PR yang ditolak kembali menjadi draft setelah diperbaiki
            if ($pr->status === PurchaseRequest::STATUS_REJECTED) {
                $pr->status = PurchaseRequest::STATUS_DRAFT;
                $pr->rejection_reason = null;
            }
            $pr->save();

            if (isset($validated['items'])) {
                $pr->items()->delete();
                $this->syncItems($pr, $validated['items']);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Purchase request updated successfully',
            'data' => $pr->fresh('items'),
        ]);
    }

    /** DELETE /api/purchase-requests/{id} */
    public function destroy(Request $request, $id): JsonResponse
    {
        $pr = PurchaseRequest::findOrFail($id);
        $user = $request->user();

        // FIX: dulu tanpa cek pemilik -> siapa pun bisa menghapus draft orang lain
        if (!$pr->isOwnedBy($user) && !$user->isAdmin()) {
            throw new BusinessRuleException('Unauthorized', 403);
        }
        if ($pr->status !== PurchaseRequest::STATUS_DRAFT) {
            throw new BusinessRuleException('Hanya PR berstatus draft yang bisa dihapus');
        }

        $prNumber = $pr->pr_number;
        $pr->delete();

        return response()->json([
            'success' => true,
            'message' => "Purchase request {$prNumber} deleted successfully",
        ]);
    }

    /** POST /api/purchase-requests/{id}/submit */
    public function submit(Request $request, $id): JsonResponse
    {
        $pr = PurchaseRequest::findOrFail($id);

        if (!$pr->isOwnedBy($request->user())) {
            throw new BusinessRuleException('Unauthorized', 403);
        }

        $this->approvalService->submitForApproval($pr, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Purchase request submitted for approval',
            'data' => $pr->fresh(['items', 'approvalWorkflows.approver']),
        ]);
    }

    /** POST /api/purchase-requests/{id}/approve  (level opsional; otomatis dari giliran user) */
    public function approve(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'level' => 'sometimes|integer|in:1,2,3',
            'comments' => 'nullable|string',
        ]);

        $pr = PurchaseRequest::findOrFail($id);

        $this->approvalService->approve(
            $pr,
            $request->user(),
            $validated['comments'] ?? null,
            isset($validated['level']) ? (int) $validated['level'] : null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Purchase request approved successfully',
            'data' => $pr->fresh(['approvalWorkflows.approver']),
        ]);
    }

    /** POST /api/purchase-requests/{id}/reject */
    public function reject(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'level' => 'sometimes|integer|in:1,2,3',
            'reason' => 'required|string|min:10',
        ]);

        $pr = PurchaseRequest::findOrFail($id);

        $this->approvalService->reject(
            $pr,
            $request->user(),
            $validated['reason'],
            isset($validated['level']) ? (int) $validated['level'] : null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Purchase request rejected successfully',
            'data' => $pr->fresh(['approvalWorkflows.approver']),
        ]);
    }

    // ------------------------------------------------------------------

    private function authorizeView(Request $request, PurchaseRequest $pr): void
    {
        if (!$pr->canBeViewedBy($request->user())) {
            throw new BusinessRuleException('Anda tidak berhak mengakses PR ini', 403);
        }
    }

    /** Buat item PR + hitung ulang total. total_price dihitung oleh model (event saving). */
    private function syncItems(PurchaseRequest $pr, array $items): void
    {
        foreach ($items as $item) {
            PurchaseRequestItem::create([
                'pr_id' => $pr->id,
                'item_name' => $item['item_name'],
                'description' => $item['description'] ?? null,
                'quantity' => $item['quantity'],
                'unit' => $item['unit'],
                'unit_price' => $item['unit_price'],
                'specification' => $item['specification'] ?? null,
            ]);
        }

        $pr->update(['total_amount' => $pr->calculateTotal()]);
    }
}
