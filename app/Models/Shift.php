<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    protected $fillable = ['name', 'start_time', 'end_time', 'hourly_rate', 'created_at', 'updated_at'];

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }
}
