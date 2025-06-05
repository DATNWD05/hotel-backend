<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = ['MaNV', 'user_id', 'name', 'email', 'role_id', 'birthday', 'gender', 'phone', 'address', 'hire_date', 'department_id', 'status', 'cccd'];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($employee) {
            if (empty($employee->MaNV)) {
                $lastEmployee = Employee::orderByDesc('id')->first();

                $nextId = $lastEmployee
                    ? ((int)filter_var($lastEmployee->MaNV, FILTER_SANITIZE_NUMBER_INT) + 1)
                    : 1;

                $employee->MaNV = 'NV' . str_pad($nextId, 2, '0', STR_PAD_LEFT);
            }
        });
    }


    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
