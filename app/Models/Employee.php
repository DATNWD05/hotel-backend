<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = ['first_name', 'last_name', 'email', 'phone', 'department_id', 'hire_date', 'status'];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
