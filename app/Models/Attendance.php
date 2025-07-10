<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $fillable = [
        'employee_id',
        'shift_id',
        'work_date',
        'check_in',
        'check_out',
        'worked_hours',
        'late_minutes',
        'early_leave_minutes',
        'created_at',
        'updated_at',
    ];
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }
}
