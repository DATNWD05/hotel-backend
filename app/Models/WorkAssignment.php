<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkAssignment extends Model
{
    protected $fillable = ['employee_id', 'shift_id', 'work_date', ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }
}
