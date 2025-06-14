<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\RoomType;
use App\Models\Floor;

class Room extends Model
{
    protected $fillable = ['room_number', 'room_type_id', 'status', 'image'];

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);  // Mối quan hệ với loại phòng
    }

    // public function floor()
    // {
    //     return $this->belongsTo(Floor::class);    // Mối quan hệ với tầng
    // }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
