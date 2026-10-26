<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LeaveController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $employee = $user->employee;

        $mine = $employee
            ? $employee->leaveRequests()->latest()->paginate(10, ['*'], 'mine_page')
            : null;

        $toDecide = null;
        if ($user->isHrStaff() || $user->isManager()) {
            $toDecide = LeaveRequest::with('employee.user')
                ->where('status', LeaveRequest::STATUS_PENDING)
                ->when($employee, fn ($q) => $q->where('employee_id', '!=', $employee->id))
                ->when(!$user->isHrStaff(), fn ($q) => $q->whereHas('employee.user', fn ($u) => $u->where('manager_id', $user->id)))
                ->orderBy('start_date')
                ->get();
        }

        return view('leaves.index', [
            'employee' => $employee,
            'mine' => $mine,
            'toDecide' => $toDecide,
            'remaining' => $employee?->remainingAnnualLeave(),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $employee = $request->user()->employee;
        if (!$employee) {
            return redirect()->route('hr.leaves.index')->with('error', 'Akun Anda belum terhubung ke data karyawan.');
        }

        return view('leaves.create', ['remaining' => $employee->remainingAnnualLeave()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $employee = $request->user()->employee;
        if (!$employee) {
            return redirect()->route('hr.leaves.index')->with('error', 'Akun Anda belum terhubung ke data karyawan.');
        }

        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(config('hr.leave_types')))],
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|max:500',
        ]);

        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->startOfDay();
        $days = LeaveRequest::countWorkdays($start, $end);

        if ($days < 1) {
            return back()->withInput()->withErrors(['start_date' => 'Rentang tanggal tidak mengandung hari kerja (Senin–Jumat).']);
        }

        if ($data['type'] === 'tahunan') {
            if ($start->year !== $end->year) {
                return back()->withInput()->withErrors(['end_date' => 'Cuti tahunan tidak boleh melewati pergantian tahun. Pisahkan pengajuannya.']);
            }
            $remaining = $employee->remainingAnnualLeave($start->year);
            if ($days > $remaining) {
                return back()->withInput()->withErrors(['end_date' => "Sisa cuti tahunan {$start->year} hanya {$remaining} hari, sedangkan pengajuan {$days} hari."]);
            }
        }

        $overlap = $employee->leaveRequests()
            ->whereIn('status', [LeaveRequest::STATUS_PENDING, LeaveRequest::STATUS_APPROVED])
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->exists();
        if ($overlap) {
            return back()->withInput()->withErrors(['start_date' => 'Tanggal bertabrakan dengan pengajuan lain yang masih aktif.']);
        }

        $employee->leaveRequests()->create([
            'type' => $data['type'],
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'days' => $days,
            'reason' => $data['reason'],
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        return redirect()->route('hr.leaves.index')->with('success', "Pengajuan {$days} hari kerja berhasil dikirim.");
    }

    public function approve(Request $request, LeaveRequest $leave): RedirectResponse
    {
        return $this->decide($request, $leave, LeaveRequest::STATUS_APPROVED);
    }

    public function reject(Request $request, LeaveRequest $leave): RedirectResponse
    {
        $request->validate(['decision_note' => 'required|string|max:500']);

        return $this->decide($request, $leave, LeaveRequest::STATUS_REJECTED);
    }

    public function cancel(Request $request, LeaveRequest $leave): RedirectResponse
    {
        $employee = $request->user()->employee;

        if (!$employee || (int) $leave->employee_id !== (int) $employee->id) {
            abort(403);
        }
        if ($leave->status !== LeaveRequest::STATUS_PENDING) {
            return back()->with('error', 'Hanya pengajuan berstatus menunggu yang bisa dibatalkan.');
        }

        $leave->update(['status' => LeaveRequest::STATUS_CANCELLED]);

        return back()->with('success', 'Pengajuan dibatalkan.');
    }

    private function decide(Request $request, LeaveRequest $leave, string $status): RedirectResponse
    {
        $user = $request->user();

        if (!$this->canDecide($user, $leave)) {
            abort(403, 'Anda tidak berwenang memproses pengajuan ini.');
        }
        if ($leave->status !== LeaveRequest::STATUS_PENDING) {
            return back()->with('error', 'Pengajuan ini sudah diproses.');
        }

        // cek ulang kuota saat menyetujui cuti tahunan
        if ($status === LeaveRequest::STATUS_APPROVED && $leave->type === 'tahunan') {
            $remaining = $leave->employee->remainingAnnualLeave($leave->start_date->year);
            if ($leave->days > $remaining) {
                return back()->with('error', "Sisa cuti karyawan hanya {$remaining} hari; tidak cukup untuk {$leave->days} hari.");
            }
        }

        $leave->update([
            'status' => $status,
            'decided_by_id' => $user->id,
            'decided_at' => now(),
            'decision_note' => $request->input('decision_note'),
        ]);

        return back()->with('success', $status === LeaveRequest::STATUS_APPROVED ? 'Pengajuan disetujui.' : 'Pengajuan ditolak.');
    }

    private function canDecide(User $user, LeaveRequest $leave): bool
    {
        $leave->loadMissing('employee.user');

        // tidak boleh memproses pengajuan sendiri
        if ((int) $leave->employee->user_id === (int) $user->id) {
            return false;
        }

        if ($user->isHrStaff()) {
            return true;
        }

        return $user->isManager() && (int) $leave->employee->user->manager_id === (int) $user->id;
    }
}
