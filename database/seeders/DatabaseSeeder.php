<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

/**
 * Data awal untuk testing. Semua user memakai password: password
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = 'password'; // otomatis di-hash oleh cast 'hashed' di model User

        $make = fn (string $email, string $name, string $role, ?string $dept, ?int $managerId = null) =>
            User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => $password,
                'role' => $role,
                'department' => $dept,
                'manager_id' => $managerId,
                'is_active' => true,
            ]);

        $make('admin@salaam.test', 'Admin Sistem', 'admin', 'IT');
        $make('director@salaam.test', 'Diana Direktur', 'director', 'Management');
        $make('finance@salaam.test', 'Fajar Finance', 'finance', 'Finance');
        $manager = $make('manager@salaam.test', 'Maya Manager', 'manager', 'IT');
        $make('requester@salaam.test', 'Rudi Requester', 'requester', 'IT', $manager->id);
        $make('hr@salaam.test', 'Hana HR', 'hr', 'HR');

        // ---- Data karyawan (modul HR): email => [jabatan, gaji pokok, tunjangan, status] ----
        $profiles = [
            'admin@salaam.test' => ['Administrator Sistem', 12000000, 1000000, 'tetap'],
            'director@salaam.test' => ['Direktur Utama', 30000000, 5000000, 'tetap'],
            'finance@salaam.test' => ['Staf Finance', 10000000, 1000000, 'tetap'],
            'manager@salaam.test' => ['IT Manager', 18000000, 2500000, 'tetap'],
            'requester@salaam.test' => ['Staf IT', 8000000, 750000, 'kontrak'],
            'hr@salaam.test' => ['HR Officer', 10000000, 1000000, 'tetap'],
        ];
        foreach ($profiles as $email => [$position, $basic, $allowance, $status]) {
            $user = User::where('email', $email)->first();
            if (!$user) {
                continue;
            }
            $employee = Employee::firstOrNew(['user_id' => $user->id]);
            if (!$employee->exists) {
                $employee->employee_number = Employee::nextNumber();
                $employee->join_date = now()->subYear()->toDateString();
            }
            $employee->fill([
                'position' => $position,
                'basic_salary' => $basic,
                'allowance' => $allowance,
                'employment_status' => $status,
                'leave_quota' => config('hr.annual_leave_quota', 12),
            ])->save();
        }

        $vendors = [
            ['V001', 'PT Mitra Jaya Indonesia', 'contact@mitrajaya.co.id', '0274-1234567', 'Jl. Magelang No. 123', 'NET 30'],
            ['V002', 'CV Sumber Barang', 'admin@sumberbarang.com', '0274-7654321', 'Jl. Diponegoro No. 45', 'COD'],
            ['V003', 'PT Bina Teknologi Sejahtera', 'sales@binatek.id', '0274-9876543', 'Jl. Ahmad Yani No. 78', 'NET 60'],
        ];
        foreach ($vendors as [$code, $name, $email, $phone, $address, $terms]) {
            Vendor::updateOrCreate(['code' => $code], [
                'name' => $name, 'email' => $email, 'phone' => $phone, 'address' => $address,
                'city' => 'Semarang', 'country' => 'Indonesia', 'payment_terms' => $terms, 'is_active' => true,
            ]);
        }
    }
}
