<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class RoomType extends Model
{
    protected $fillable = ['name', 'description'];

    public function rooms()
    {
        return $this->hasMany(Room::class);  // Mối quan hệ 1-n với phòng
    }
}
