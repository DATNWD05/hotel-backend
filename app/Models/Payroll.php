<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    protected $fillable = [
        'employee_id',
        'month',
        'total_hours',
        'overtime_hours',      // ✅ mới
        'total_days',
        'total_salary',
        'overtime_salary',   // ✅ thêm vào đây
        'bonus',
        'penalty',
        'final_salary',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
