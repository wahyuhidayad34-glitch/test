<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Payroll;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;
    private User $manager;
    private User $staff;
    private User $otherManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hr = $this->makeEmployeeUser('hr', 'HR');
        $this->manager = $this->makeEmployeeUser('manager', 'IT');
        $this->otherManager = $this->makeEmployeeUser('manager', 'Finance');
        $this->staff = $this->makeEmployeeUser('requester', 'IT', $this->manager->id);
    }

    private function makeEmployeeUser(string $role, string $dept, ?int $managerId = null): User
    {
        $user = User::factory()->role($role, $dept)->create(['manager_id' => $managerId]);
        Employee::create([
            'user_id' => $user->id,
            'employee_number' => Employee::nextNumber(),
            'join_date' => now()->subYears(2)->toDateString(),
            'basic_salary' => 10000000,
            'allowance' => 1000000,
            'leave_quota' => 12,
        ]);

        return $user;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('hr.login'));
        $this->get('/login')->assertOk();
    }

    public function test_user_can_login_and_see_dashboard(): void
    {
        $this->post('/login', ['email' => $this->staff->email, 'password' => 'password'])
            ->assertRedirect(route('hr.dashboard'));

        $this->get('/dashboard')->assertOk()->assertSee($this->staff->name);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->staff->update(['is_active' => false]);

        $this->post('/login', ['email' => $this->staff->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_regular_staff_cannot_open_employee_management(): void
    {
        $this->actingAs($this->staff)->get('/employees')->assertForbidden();
        $this->actingAs($this->hr)->get('/employees')->assertOk();
    }

    public function test_check_in_once_per_day_and_check_out(): void
    {
        $this->actingAs($this->staff)->post('/attendance/check-in')->assertSessionHas('success');
        $this->assertSame(1, Attendance::count());

        $this->post('/attendance/check-in')->assertSessionHas('error');
        $this->assertSame(1, Attendance::count());

        $this->post('/attendance/check-out')->assertSessionHas('success');
        $this->assertNotNull(Attendance::first()->check_out);
    }

    public function test_leave_is_approved_by_direct_manager_only(): void
    {
        $monday = now()->next(Carbon::MONDAY);

        $this->actingAs($this->staff)->post('/leaves', [
            'type' => 'tahunan',
            'start_date' => $monday->toDateString(),
            'end_date' => $monday->copy()->addDay()->toDateString(),
            'reason' => 'Acara keluarga',
        ])->assertRedirect(route('hr.leaves.index'));

        $leave = LeaveRequest::firstOrFail();
        $this->assertSame(2, $leave->days);
        $this->assertSame('pending', $leave->status);

        // pemohon tidak boleh menyetujui sendiri, manager lain juga tidak
        $this->actingAs($this->staff)->post("/leaves/{$leave->id}/approve")->assertForbidden();
        $this->actingAs($this->otherManager)->post("/leaves/{$leave->id}/approve")->assertForbidden();

        $this->actingAs($this->manager)->post("/leaves/{$leave->id}/approve")->assertSessionHas('success');
        $this->assertSame('approved', $leave->fresh()->status);
        $this->assertSame(10, $this->staff->employee->remainingAnnualLeave($monday->year));
    }

    public function test_annual_leave_cannot_exceed_remaining_quota(): void
    {
        $this->staff->employee->update(['leave_quota' => 1]);
        $monday = now()->next(Carbon::MONDAY);

        $this->actingAs($this->staff)->post('/leaves', [
            'type' => 'tahunan',
            'start_date' => $monday->toDateString(),
            'end_date' => $monday->copy()->addDays(2)->toDateString(),
            'reason' => 'Liburan',
        ])->assertSessionHasErrors('end_date');

        $this->assertSame(0, LeaveRequest::count());
    }

    public function test_hr_generates_payroll_and_employee_sees_only_paid_slip(): void
    {
        $period = now()->subMonthNoOverflow()->format('Y-m');

        $this->actingAs($this->hr)->post('/payrolls/generate', ['period' => $period])->assertSessionHas('success');

        $payroll = Payroll::where('employee_id', $this->staff->employee->id)->where('period', $period)->firstOrFail();
        $this->assertSame(11000000.0, $payroll->gross_pay);
        $this->assertSame(300000.0, $payroll->bpjs_deduction); // 3% x 10 jt
        $this->assertSame('draft', $payroll->status);

        $start = Carbon::createFromFormat('Y-m-d', $period . '-01');
        $this->assertSame(LeaveRequest::countWorkdays($start, $start->copy()->endOfMonth()), $payroll->absence_days);

        // slip draft belum boleh dilihat karyawan
        $this->actingAs($this->staff)->get("/payrolls/{$payroll->id}")->assertForbidden();

        $this->actingAs($this->hr)->post("/payrolls/{$payroll->id}/pay")->assertSessionHas('success');
        $this->actingAs($this->staff)->get("/payrolls/{$payroll->id}")->assertOk();

        // karyawan lain tidak boleh melihat slip orang lain
        $this->actingAs($this->otherManager)->get("/payrolls/{$payroll->id}")->assertForbidden();
    }

    public function test_hr_can_create_employee(): void
    {
        $this->actingAs($this->hr)->post('/employees', [
            'name' => 'Karyawan Baru',
            'email' => 'baru@salaam.test',
            'password' => 'rahasia123',
            'role' => 'requester',
            'department' => 'IT',
            'manager_id' => $this->manager->id,
            'join_date' => now()->toDateString(),
            'employment_status' => 'probation',
            'basic_salary' => 7000000,
            'allowance' => 500000,
            'leave_quota' => 12,
        ])->assertRedirect();

        $user = User::where('email', 'baru@salaam.test')->firstOrFail();
        $this->assertNotNull($user->employee);
        $this->assertSame(7000000.0, $user->employee->basic_salary);
    }
}
