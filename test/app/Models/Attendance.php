<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    public const STATUS_PRESENT = 'hadir';
    public const STATUS_LATE = 'terlambat';

    protected $fillable = ['employee_id', 'work_date', 'check_in', 'check_out', 'status', 'note'];

    protected $casts = [
        'work_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
