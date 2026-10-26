<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Hanya untuk role admin & hr (lihat routes/web.php) */
class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $query = Employee::with('user')->orderBy('employee_number');

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($q) use ($search) {
                $q->where('employee_number', 'like', "%{$search}%")
                    ->orWhere('position', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }
        if ($department = $request->query('department')) {
            $query->whereHas('user', fn ($u) => $u->where('department', $department));
        }

        return view('employees.index', [
            'employees' => $query->paginate(15)->withQueryString(),
            'departments' => User::whereNotNull('department')->distinct()->orderBy('department')->pluck('department'),
        ]);
    }

    public function create(): View
    {
        return view('employees.form', [
            'employee' => new Employee([
                'join_date' => today(),
                'employment_status' => 'probation',
                'leave_quota' => config('hr.annual_leave_quota'),
            ]),
            'managers' => $this->managers(),
            'roles' => $this->assignableRoles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());

        $employee = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => $data['role'],
                'department' => $data['department'],
                'manager_id' => $data['manager_id'] ?? null,
                'is_active' => true,
            ]);

            return Employee::create($this->employeeAttributes($data) + [
                'user_id' => $user->id,
                'employee_number' => Employee::nextNumber(),
            ]);
        });

        return redirect()->route('hr.employees.show', $employee)->with('success', 'Karyawan berhasil ditambahkan.');
    }

    public function show(Employee $employee): View
    {
        $employee->load('user.manager');

        return view('employees.show', [
            'employee' => $employee,
            'remainingLeave' => $employee->remainingAnnualLeave(),
            'recentLeaves' => $employee->leaveRequests()->latest()->limit(5)->get(),
        ]);
    }

    public function edit(Employee $employee): View
    {
        return view('employees.form', [
            'employee' => $employee->load('user'),
            'managers' => $this->managers($employee->user_id),
            'roles' => $this->assignableRoles(),
        ]);
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $data = $request->validate($this->rules($employee));

        // hanya admin yang boleh mengubah role menjadi/dari admin
        if (!$request->user()->isAdmin() && ($data['role'] === 'admin' || $employee->user->role === 'admin')) {
            abort(403, 'Hanya admin yang boleh mengubah role admin.');
        }

        DB::transaction(function () use ($data, $employee) {
            $userData = [
                'name' => $data['name'],
                'email' => $data['email'],
                'role' => $data['role'],
                'department' => $data['department'],
                'manager_id' => $data['manager_id'] ?? null,
            ];
            if (!empty($data['password'])) {
                $userData['password'] = $data['password'];
            }
            $employee->user->update($userData);
            $employee->update($this->employeeAttributes($data));
        });

        return redirect()->route('hr.employees.show', $employee)->with('success', 'Data karyawan diperbarui.');
    }

    /** Aktifkan / nonaktifkan akun (karyawan tidak dihapus agar riwayat tetap ada) */
    public function toggleActive(Request $request, Employee $employee): RedirectResponse
    {
        $user = $employee->user;

        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Anda tidak bisa menonaktifkan akun sendiri.');
        }

        $user->update(['is_active' => !$user->is_active]);

        return back()->with('success', $user->is_active ? 'Karyawan diaktifkan.' : 'Karyawan dinonaktifkan.');
    }

    private function rules(?Employee $employee = null): array
    {
        $userId = $employee?->user_id;
        $managerRules = ['nullable', 'integer', 'exists:users,id'];
        if ($userId) {
            $managerRules[] = Rule::notIn([$userId]); // tidak boleh menjadi atasan diri sendiri
        }

        return [
            'name' => 'required|string|max:150',
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($userId)],
            'password' => [$employee ? 'nullable' : 'required', 'string', 'min:8'],
            'role' => ['required', Rule::in(array_keys($this->assignableRoles()))],
            'department' => 'required|string|max:100',
            'manager_id' => $managerRules,
            'position' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'gender' => ['nullable', Rule::in(['L', 'P'])],
            'birth_date' => 'nullable|date|before:today',
            'address' => 'nullable|string|max:500',
            'join_date' => 'required|date',
            'employment_status' => ['required', Rule::in(array_keys(config('hr.employment_statuses')))],
            'basic_salary' => 'required|numeric|min:0|max:9999999999',
            'allowance' => 'nullable|numeric|min:0|max:9999999999',
            'leave_quota' => 'required|integer|min:0|max:60',
        ];
    }

    private function employeeAttributes(array $data): array
    {
        return [
            'position' => $data['position'] ?? null,
            'phone' => $data['phone'] ?? null,
            'gender' => $data['gender'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'address' => $data['address'] ?? null,
            'join_date' => $data['join_date'],
            'employment_status' => $data['employment_status'],
            'basic_salary' => $data['basic_salary'],
            'allowance' => $data['allowance'] ?? 0,
            'leave_quota' => $data['leave_quota'],
        ];
    }

    private function managers(?int $exceptUserId = null)
    {
        return User::active()
            ->whereIn('role', ['manager', 'director', 'admin', 'hr'])
            ->when($exceptUserId, fn ($q) => $q->where('id', '!=', $exceptUserId))
            ->orderBy('name')
            ->get(['id', 'name', 'role']);
    }

    private function assignableRoles(): array
    {
        $roles = [
            'requester' => 'Karyawan',
            'manager' => 'Manager',
            'hr' => 'HR',
            'finance' => 'Finance',
            'director' => 'Direktur',
        ];

        if (auth()->user()?->isAdmin()) {
            $roles['admin'] = 'Admin';
        }

        return $roles;
    }
}
