<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payroll extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'employee_id', 'period', 'basic_salary', 'allowance', 'gross_pay', 'bpjs_deduction',
        'absence_days', 'absence_deduction', 'total_deduction', 'net_pay',
        'status', 'paid_at', 'generated_by_id',
    ];

    protected $casts = [
        'absence_days' => 'integer',
        'basic_salary' => 'float',
        'allowance' => 'float',
        'gross_pay' => 'float',
        'bpjs_deduction' => 'float',
        'absence_deduction' => 'float',
        'total_deduction' => 'float',
        'net_pay' => 'float',
        'paid_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
