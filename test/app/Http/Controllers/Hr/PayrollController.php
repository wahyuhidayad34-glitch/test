<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Payroll;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PayrollController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $seesAll = $user->isHrStaff() || in_array($user->role, ['finance', 'director'], true);
        $period = $this->validPeriod($request->query('period')) ?? now()->format('Y-m');

        if ($seesAll) {
            $payrolls = Payroll::with('employee.user')
                ->where('payrolls.period', $period)
                ->join('employees', 'employees.id', '=', 'payrolls.employee_id')
                ->orderBy('employees.employee_number')
                ->select('payrolls.*')
                ->get();
        } else {
            $payrolls = $user->employee
                ? $user->employee->payrolls()->where('status', Payroll::STATUS_PAID)->orderByDesc('period')->get()
                : collect();
        }

        return view('payrolls.index', [
            'payrolls' => $payrolls,
            'period' => $period,
            'seesAll' => $seesAll,
            'totalNet' => $seesAll ? $payrolls->sum('net_pay') : null,
        ]);
    }

    public function generate(Request $request, PayrollService $service): RedirectResponse
    {
        $data = $request->validate(['period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']]);

        if (Carbon::createFromFormat('Y-m-d', $data['period'] . '-01')->gt(now()->startOfMonth())) {
            return back()->with('error', 'Periode gaji tidak boleh di masa depan.');
        }

        $count = $service->generate($data['period'], $request->user());

        return redirect()->route('hr.payrolls.index', ['period' => $data['period']])
            ->with('success', "{$count} slip gaji periode {$data['period']} dibuat/diperbarui (slip yang sudah dibayar tidak diubah).");
    }

    public function show(Request $request, Payroll $payroll): View
    {
        $user = $request->user();
        $payroll->load('employee.user');

        $isOwner = $user->employee && (int) $user->employee->id === (int) $payroll->employee_id;
        $privileged = $user->isHrStaff() || in_array($user->role, ['finance', 'director'], true);

        if (!$isOwner && !$privileged) {
            abort(403);
        }
        // karyawan hanya boleh melihat slip yang sudah dibayar
        if ($isOwner && !$privileged && $payroll->status !== Payroll::STATUS_PAID) {
            abort(403, 'Slip gaji belum dirilis.');
        }

        return view('payrolls.show', ['payroll' => $payroll]);
    }

    public function markPaid(Request $request, Payroll $payroll): RedirectResponse
    {
        if ($payroll->status === Payroll::STATUS_PAID) {
            return back()->with('error', 'Slip ini sudah ditandai dibayar.');
        }

        $payroll->update(['status' => Payroll::STATUS_PAID, 'paid_at' => now()]);

        return back()->with('success', 'Slip gaji ditandai sudah dibayar.');
    }

    private function validPeriod(?string $value): ?string
    {
        return $value && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) ? $value : null;
    }
}
