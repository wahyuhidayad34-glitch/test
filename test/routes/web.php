<?php

use App\Http\Controllers\Hr\AttendanceController;
use App\Http\Controllers\Hr\AuthWebController;
use App\Http\Controllers\Hr\DashboardController;
use App\Http\Controllers\Hr\EmployeeController;
use App\Http\Controllers\Hr\LeaveController;
use App\Http\Controllers\Hr\PayrollController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('hr.dashboard'));

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthWebController::class, 'showLogin'])->name('hr.login');
    Route::post('login', [AuthWebController::class, 'login'])->middleware('throttle:10,1')->name('hr.login.attempt');
});

Route::middleware('auth')->name('hr.')->group(function () {
    Route::post('logout', [AuthWebController::class, 'logout'])->name('logout');
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ---- Karyawan (admin & hr) ----
    Route::middleware('role:admin,hr')->prefix('employees')->name('employees.')->group(function () {
        Route::get('/', [EmployeeController::class, 'index'])->name('index');
        Route::get('create', [EmployeeController::class, 'create'])->name('create');
        Route::post('/', [EmployeeController::class, 'store'])->name('store');
        Route::get('{employee}', [EmployeeController::class, 'show'])->whereNumber('employee')->name('show');
        Route::get('{employee}/edit', [EmployeeController::class, 'edit'])->whereNumber('employee')->name('edit');
        Route::put('{employee}', [EmployeeController::class, 'update'])->whereNumber('employee')->name('update');
        Route::post('{employee}/toggle-active', [EmployeeController::class, 'toggleActive'])->whereNumber('employee')->name('toggle-active');
    });

    // ---- Absensi ----
    Route::prefix('attendance')->name('attendance.')->group(function () {
        Route::get('/', [AttendanceController::class, 'index'])->name('index');
        Route::post('check-in', [AttendanceController::class, 'checkIn'])->name('check-in');
        Route::post('check-out', [AttendanceController::class, 'checkOut'])->name('check-out');
    });

    // ---- Cuti / Izin ----
    Route::prefix('leaves')->name('leaves.')->group(function () {
        Route::get('/', [LeaveController::class, 'index'])->name('index');
        Route::get('create', [LeaveController::class, 'create'])->name('create');
        Route::post('/', [LeaveController::class, 'store'])->name('store');
        Route::post('{leave}/approve', [LeaveController::class, 'approve'])->whereNumber('leave')->name('approve');
        Route::post('{leave}/reject', [LeaveController::class, 'reject'])->whereNumber('leave')->name('reject');
        Route::post('{leave}/cancel', [LeaveController::class, 'cancel'])->whereNumber('leave')->name('cancel');
    });

    // ---- Penggajian ----
    Route::prefix('payrolls')->name('payrolls.')->group(function () {
        Route::get('/', [PayrollController::class, 'index'])->name('index');
        Route::post('generate', [PayrollController::class, 'generate'])->middleware('role:admin,hr')->name('generate');
        Route::get('{payroll}', [PayrollController::class, 'show'])->whereNumber('payroll')->name('show');
        Route::post('{payroll}/pay', [PayrollController::class, 'markPaid'])->middleware('role:admin,hr,finance')->whereNumber('payroll')->name('pay');
    });
});
