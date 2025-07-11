<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeFace extends Model
{
    protected $fillable = ['employee_id', 'image_path', 'created_at', 'updated_at'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
    
}

