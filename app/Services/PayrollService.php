<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Payroll;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    /**
     * Buat / hitung ulang slip gaji seluruh karyawan aktif untuk periode (YYYY-MM).
     * Slip yang sudah berstatus "paid" tidak diubah.
     *
     * Rumus sederhana (belum termasuk PPh 21):
     *  - gross      = gaji pokok + tunjangan
     *  - BPJS       = gaji pokok x config('hr.bpjs_rate')
     *  - alpha      = hari kerja tanpa absensi dan tanpa cuti/izin/sakit yang disetujui
     *  - potongan   = alpha x (gaji pokok / config('hr.work_days_per_month'))
     *  - net        = gross - BPJS - potongan alpha
     *
     * @return int jumlah slip yang dibuat/diperbarui
     */
    public function generate(string $period, User $by): int
    {
        $start = Carbon::createFromFormat('Y-m-d', $period . '-01')->startOfDay();
        $end = $start->copy()->endOfMonth();
        // hari ini belum dihitung alpha (karyawan mungkin belum check-in)
        $cutoff = $end->isFuture() ? today()->subDay() : $end;

        $count = 0;

        DB::transaction(function () use ($period, $by, $start, $cutoff, &$count) {
            $employees = Employee::with('user')
                ->whereHas('user', fn ($q) => $q->where('is_active', true))
                ->get();

            foreach ($employees as $employee) {
                $existing = Payroll::where('employee_id', $employee->id)->where('period', $period)->first();
                if ($existing && $existing->status === Payroll::STATUS_PAID) {
                    continue;
                }

                $absence = $this->countAbsence($employee, $start, $cutoff);

                $basic = (float) $employee->basic_salary;
                $allowance = (float) $employee->allowance;
                $gross = $basic + $allowance;
                $bpjs = round($basic * (float) config('hr.bpjs_rate'));
                $daily = $basic / max(1, (int) config('hr.work_days_per_month'));
                $absenceDeduction = round($absence * $daily);
                $totalDeduction = $bpjs + $absenceDeduction;

                Payroll::updateOrCreate(
                    ['employee_id' => $employee->id, 'period' => $period],
                    [
                        'basic_salary' => $basic,
                        'allowance' => $allowance,
                        'gross_pay' => $gross,
                        'bpjs_deduction' => $bpjs,
                        'absence_days' => $absence,
                        'absence_deduction' => $absenceDeduction,
                        'total_deduction' => $totalDeduction,
                        'net_pay' => max(0, $gross - $totalDeduction),
                        'status' => Payroll::STATUS_DRAFT,
                        'generated_by_id' => $by->id,
                    ]
                );
                $count++;
            }
        });

        return $count;
    }

    private function countAbsence(Employee $employee, Carbon $start, Carbon $cutoff): int
    {
        $from = $employee->join_date && $employee->join_date->gt($start) ? $employee->join_date->copy() : $start->copy();
        if ($from->gt($cutoff)) {
            return 0;
        }

        $attended = Attendance::where('employee_id', $employee->id)
            ->whereBetween('work_date', [$from->toDateString(), $cutoff->toDateString()])
            ->pluck('work_date')
            ->map(fn ($d) => $d->toDateString())
            ->all();

        $onLeave = [];
        $leaves = LeaveRequest::where('employee_id', $employee->id)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereDate('start_date', '<=', $cutoff->toDateString())
            ->whereDate('end_date', '>=', $from->toDateString())
            ->get();
        foreach ($leaves as $leave) {
            for ($d = $leave->start_date->copy(); $d->lte($leave->end_date); $d->addDay()) {
                $onLeave[$d->toDateString()] = true;
            }
        }

        $absence = 0;
        for ($d = $from->copy(); $d->lte($cutoff); $d->addDay()) {
            if ($d->isWeekend()) {
                continue;
            }
            $key = $d->toDateString();
            if (!in_array($key, $attended, true) && !isset($onLeave[$key])) {
                $absence++;
            }
        }

        return $absence;
    }
}
