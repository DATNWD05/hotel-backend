<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Salary extends Model
{
    protected $fillable = [
        'employee_id',
        'month',
        'year',
        'total_hours',
        'total_salary'
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
