<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    protected $fillable = [
        'employee_id',
        'month',
        'total_hours',
        'total_days',
        'total_salary',
        'bonus',
        'penalty',
        'final_salary'
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
