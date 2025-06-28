<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'booking_id',
        'issued_date',
        'room_amount',
        'service_amount',
        'discount_amount',
        'deposit_amount',
        'total_amount',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
