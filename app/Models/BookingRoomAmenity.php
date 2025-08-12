<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingRoomAmenity extends Model
{
    protected $table = 'booking_room_amenities';
    protected $fillable = ['booking_id', 'room_id', 'amenity_id', 'price', 'quantity'];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
    public function room()
    {
        return $this->belongsTo(Room::class);
    }
    public function amenity()
    {
        return $this->belongsTo(Amenity::class);
    }
}
