<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Room;

class Floor extends Model
{
    protected $fillable = ['name', 'number'];

    public function rooms()
    {
        return $this->hasMany(Room::class);  // Mối quan hệ 1-n với phòng
    }
}
