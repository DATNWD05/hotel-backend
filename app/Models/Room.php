<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\RoomType;
use App\Models\Booking;

class Room extends Model
{
    use SoftDeletes;

    protected $fillable = ['room_number', 'room_type_id', 'status', 'image', 'deleted_at'];
    protected $dates = ['deleted_at'];

    // Quan hệ với loại phòng
    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }

    // Quan hệ many-to-many với booking thông qua bảng booking_room
    public function bookings()
    {
        return $this->belongsToMany(Booking::class, 'booking_room', 'room_id', 'booking_id')
            ->withTimestamps(); // nếu bảng trung gian có created_at và updated_at
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return $this->withTrashed()
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->firstOrFail();
    }
}
