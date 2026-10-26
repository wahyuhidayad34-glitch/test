<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $fillable = [
        'user_id', 'employee_number', 'position', 'phone', 'gender', 'birth_date', 'address',
        'join_date', 'employment_status', 'basic_salary', 'allowance', 'leave_quota',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'join_date' => 'date',
        'leave_quota' => 'integer',
        'basic_salary' => 'float',
        'allowance' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    public static function nextNumber(): string
    {
        return 'EMP-' . str_pad((string) (((int) static::max('id')) + 1), 4, '0', STR_PAD_LEFT);
    }

    /** Total hari cuti tahunan yang sudah disetujui pada tahun tertentu */
    public function usedAnnualLeave(?int $year = null): int
    {
        $year ??= now()->year;

        return (int) $this->leaveRequests()
            ->where('type', 'tahunan')
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereYear('start_date', $year)
            ->sum('days');
    }

    public function remainingAnnualLeave(?int $year = null): int
    {
        return max(0, $this->leave_quota - $this->usedAnnualLeave($year));
    }

    public function getStatusLabelAttribute(): string
    {
        return config('hr.employment_statuses')[$this->employment_status] ?? $this->employment_status;
    }
}
