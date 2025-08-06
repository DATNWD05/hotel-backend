<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OvertimeRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'overtime_type',
        'work_date',
        'start_datetime',
        'end_datetime',
        'reason',
    ];

    protected $casts = [
        'work_date' => 'date',
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
    ];

    // Quan hệ với Employee
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
