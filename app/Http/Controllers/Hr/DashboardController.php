<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $employee = $user->employee;
        $today = today();

        $data = [
            'employee' => $employee,
            'todayAttendance' => $employee
                ? Attendance::where('employee_id', $employee->id)->whereDate('work_date', $today)->first()
                : null,
            'remainingLeave' => $employee?->remainingAnnualLeave(),
        ];

        if ($user->isHrStaff()) {
            $activeEmployees = Employee::whereHas('user', fn ($q) => $q->where('is_active', true));
            $data['stats'] = [
                'total_employees' => (clone $activeEmployees)->count(),
                'present_today' => Attendance::whereDate('work_date', $today)->count(),
                'late_today' => Attendance::whereDate('work_date', $today)->where('status', Attendance::STATUS_LATE)->count(),
                'on_leave_today' => LeaveRequest::where('status', LeaveRequest::STATUS_APPROVED)
                    ->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today)->count(),
                'pending_leaves' => LeaveRequest::where('status', LeaveRequest::STATUS_PENDING)->count(),
            ];
        } elseif ($user->isManager()) {
            $data['pendingTeamLeaves'] = LeaveRequest::where('status', LeaveRequest::STATUS_PENDING)
                ->whereHas('employee.user', fn ($q) => $q->where('manager_id', $user->id))
                ->count();
        }

        $data['myRecentLeaves'] = $employee
            ? $employee->leaveRequests()->latest()->limit(5)->get()
            : collect();

        return view('dashboard', $data);
    }
}
