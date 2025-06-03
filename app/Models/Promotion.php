<?php

// app/Models/Promotion.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Promotion extends Model
{
    protected $fillable = [
        'code',
        'description',
        'discount_type',
        'discount_value',
        'start_date',
        'end_date',
        'usage_limit',
        'used_count',
        'is_active',
    ];

    // Chỉ load promotions đang active
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function bookings()
    {
        return $this->belongsToMany(
            Booking::class,
            'booking_promotions'
        )
            ->withPivot('applied_at')
            ->withTimestamps();
    }

    // Kiểm tra còn hiệu lực: active, trong ngày, chưa vượt limit
    public function isValid(): bool
    {
        $today = now()->toDateString();
        return $this->is_active
            && $today >= $this->start_date
            && $today <= $this->end_date
            && $this->used_count < $this->usage_limit;
    }
}
