<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $employee = $user->employee;

        $mine = $employee
            ? Attendance::where('employee_id', $employee->id)->orderByDesc('work_date')->limit(31)->get()
            : collect();

        $all = null;
        $date = null;
        if ($user->isHrStaff()) {
            $date = $this->parseDate($request->query('date'));
            $all = Attendance::with('employee.user')
                ->whereDate('work_date', $date)
                ->orderBy('check_in')
                ->get();
        }

        return view('attendance.index', [
            'employee' => $employee,
            'today' => $employee ? $mine->first(fn ($a) => $a->work_date->isToday()) : null,
            'mine' => $mine,
            'all' => $all,
            'date' => $date,
        ]);
    }

    public function checkIn(Request $request): RedirectResponse
    {
        $employee = $request->user()->employee;
        if (!$employee) {
            return back()->with('error', 'Akun Anda belum terhubung ke data karyawan.');
        }

        if (Attendance::where('employee_id', $employee->id)->whereDate('work_date', today())->exists()) {
            return back()->with('error', 'Anda sudah check-in hari ini.');
        }

        $now = now();
        $limit = today()->setTimeFromTimeString(config('hr.work_start'));

        Attendance::create([
            'employee_id' => $employee->id,
            'work_date' => $now->toDateString(),
            'check_in' => $now->format('H:i:s'),
            'status' => $now->gt($limit) ? Attendance::STATUS_LATE : Attendance::STATUS_PRESENT,
        ]);

        return back()->with('success', 'Check-in berhasil pukul ' . $now->format('H:i') . '.');
    }

    public function checkOut(Request $request): RedirectResponse
    {
        $employee = $request->user()->employee;
        $attendance = $employee
            ? Attendance::where('employee_id', $employee->id)->whereDate('work_date', today())->first()
            : null;

        if (!$attendance) {
            return back()->with('error', 'Anda belum check-in hari ini.');
        }
        if ($attendance->check_out) {
            return back()->with('error', 'Anda sudah check-out hari ini.');
        }

        $attendance->update(['check_out' => now()->format('H:i:s')]);

        return back()->with('success', 'Check-out berhasil pukul ' . now()->format('H:i') . '.');
    }

    private function parseDate(?string $value): Carbon
    {
        try {
            return $value ? Carbon::parse($value)->startOfDay() : today();
        } catch (\Throwable) {
            return today();
        }
    }
}
