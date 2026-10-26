<?php

use App\Http\Controllers\Api\ApprovalController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\PurchaseRequestController;
use Illuminate\Support\Facades\Route;

// ============================================
// PUBLIC
// ============================================
// throttle: maksimal 10 percobaan login per menit per IP
Route::post('login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1')
    ->name('login');

// ============================================
// PROTECTED (Sanctum token)
// ============================================
Route::middleware('auth:sanctum')->group(function () {

    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('me', [AuthController::class, 'me'])->name('me');

    // ---- Purchase Requests ----
    Route::prefix('purchase-requests')->group(function () {
        Route::get('/', [PurchaseRequestController::class, 'index'])->name('purchase-requests.index');
        Route::post('/', [PurchaseRequestController::class, 'store'])->name('purchase-requests.store');

        Route::get('{id}', [PurchaseRequestController::class, 'show'])->whereNumber('id')->name('purchase-requests.show');
        Route::put('{id}', [PurchaseRequestController::class, 'update'])->whereNumber('id')->name('purchase-requests.update');
        Route::delete('{id}', [PurchaseRequestController::class, 'destroy'])->whereNumber('id')->name('purchase-requests.destroy');

        Route::post('{id}/submit', [PurchaseRequestController::class, 'submit'])->whereNumber('id')->name('purchase-requests.submit');
        Route::post('{id}/approve', [PurchaseRequestController::class, 'approve'])->whereNumber('id')->name('purchase-requests.approve');
        Route::post('{id}/reject', [PurchaseRequestController::class, 'reject'])->whereNumber('id')->name('purchase-requests.reject');
    });

    // ---- Approvals ----
    Route::prefix('approvals')->group(function () {
        Route::get('pending', [ApprovalController::class, 'pending'])->name('approvals.pending');
        Route::get('history/{type}/{id}', [ApprovalController::class, 'history'])
            ->whereIn('type', ['pr', 'po', 'invoice'])
            ->whereNumber('id')
            ->name('approvals.history');
    });

    // ---- Dashboard ----
    Route::prefix('dashboard')->group(function () {
        Route::get('metrics', [DashboardController::class, 'metrics'])->name('dashboard.metrics');
        Route::get('pending-tasks', [DashboardController::class, 'pendingTasks'])->name('dashboard.pending-tasks');
    });

    // FUTURE: vendors, purchase-orders, goods-receipts, invoices
});

// Route uji coba hanya aktif di environment local
if (app()->environment('local')) {
    Route::get('test', fn () => response()->json([
        'message' => 'API is working!',
        'timestamp' => now(),
    ]));
}

Route::fallback(fn () => response()->json([
    'success' => false,
    'message' => 'API endpoint not found',
], 404));
