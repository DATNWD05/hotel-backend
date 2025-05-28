<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Carbon\Carbon;

class Booking extends Model
{
    protected $fillable = [
        'customer_id',
        'created_by',
        'check_in_date',
        'check_out_date',
        'status',
        'deposit_amount',
        'raw_total',
        'discount_amount',
        'total_amount',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // public function rooms(): HasMany
    // {
    //     return $this->hasMany(BookingRoom::class);
    // }

    // public function serviceOrders(): HasMany
    // {
    //     return $this->hasMany(ServiceOrder::class);
    // }

    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(
            Promotion::class,
            'booking_promotions'
        )
            ->withPivot('promotion_code', 'applied_at')
            ->withTimestamps();
    }

    // Tái tính tổng
    public function recalculateTotal(): void
    {
        $nights = Carbon::parse($this->check_in_date)
            ->diffInDays($this->check_out_date);

        $raw = $this->rooms
            ->sum(fn($br) => $br->rate * $nights)
            + $this->serviceOrders
            ->sum(fn($so) => $so->quantity * $so->service->price);

        $discount = 0;
        if ($promo = $this->promotions()->latest('pivot_applied_at')->first()) {
            if ($promo->isValid()) {
                $discount = $promo->discount_type === 'percent'
                    ? $raw * ($promo->discount_value / 100)
                    : $promo->discount_value;
            }
        }

        $this->forceFill([
            'raw_total'       => $raw,
            'discount_amount' => $discount,
            'total_amount'    => max(0, $raw - $discount),
        ])->save();
    }
}
