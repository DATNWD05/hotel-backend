<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'invoice_code',
        'booking_id',
        'issued_date',
        'room_amount',
        'service_amount',
        'amenity_amount',
        'discount_amount',
        'deposit_amount',
        'total_amount',
    ];

    protected $casts = [
        'issued_date' => 'datetime',
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
