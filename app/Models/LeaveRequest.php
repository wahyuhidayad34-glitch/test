<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'employee_id', 'type', 'start_date', 'end_date', 'days', 'reason',
        'status', 'decided_by_id', 'decided_at', 'decision_note',
    ];

    protected $casts = [
        'days' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'decided_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return config('hr.leave_types')[$this->type] ?? $this->type;
    }

    /** Hitung hari kerja (Senin–Jumat) antara dua tanggal, inklusif */
    public static function countWorkdays(Carbon $start, Carbon $end): int
    {
        $count = 0;
        for ($d = $start->copy()->startOfDay(); $d->lte($end); $d->addDay()) {
            if (!$d->isWeekend()) {
                $count++;
            }
        }

        return $count;
    }
}
