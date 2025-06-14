<?php

// app/Models/Promotion.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

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
        'status',
    ];

    protected $casts = [
        'start_date'  => 'date',
        'end_date'    => 'date',
        'usage_limit' => 'integer',
        'used_count'  => 'integer',
        'is_active'   => 'boolean',
        'status'      => 'string',
    ];

    public function syncStatus(): void
    {
        $today = Carbon::today();

        if ($this->status === 'cancelled') {
            // giữ nguyên
        } elseif ($this->used_count >= $this->usage_limit) {
            $this->status    = 'depleted';
            $this->is_active = false;
        } elseif ($today->lt($this->start_date)) {
            $this->status    = 'scheduled';
            $this->is_active = false;
        } elseif ($today->gt($this->end_date)) {
            $this->status    = 'expired';
            $this->is_active = false;
        } else {
            $this->status    = 'active';
            $this->is_active = true;
        }

        if ($this->isDirty(['status', 'is_active'])) {
            $this->save();
        }
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
        $now = now();

        return (!$this->start_date || $this->start_date <= $now)
            && (!$this->end_date || $this->end_date >= $now)
            && $this->is_active == 1;
    }
}
