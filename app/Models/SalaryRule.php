<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryRule extends Model
{
    protected $fillable = [
        'role_id',
        'overtime_multiplier',
        'late_penalty_per_minute',
        'early_leave_penalty_per_minute',
        'daily_allowance',
        'hourly_rate', // NEW
    ];

    protected $casts = [
        'overtime_multiplier' => 'decimal:2',
        'late_penalty_per_minute' => 'decimal:2',
        'early_leave_penalty_per_minute' => 'decimal:2',
        'daily_allowance' => 'decimal:2',
        'hourly_rate' => 'decimal:2', // NEW
    ];
}
