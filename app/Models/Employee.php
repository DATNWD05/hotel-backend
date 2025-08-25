<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = ['MaNV', 'user_id', 'name', 'email', 'role_id', 'birthday', 'gender', 'phone', 'address', 'hire_date', 'department_id', 'status', 'cccd', 'face_image', 'hourly_rate'];

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

    // Các mối quan hệ với Attendance và Salary
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    }

    public function faces()
    {
        return $this->hasMany(EmployeeFace::class);
    }
    public function workAssignments()
    {
        return $this->hasMany(WorkAssignment::class);
    }
}
