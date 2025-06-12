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
        'room_id',
        'created_by',
        'check_in_date',
        'check_out_date',
        'status',
        'raw_total',
        'discount_amount',
        'total_amount',
    ];

    // Quan hệ khách hàng
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    // Nhân viên tạo đơn
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Phòng được đặt
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    // Dịch vụ đã đặt (qua bảng booking_service)
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'booking_service')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    // Khuyến mãi áp dụng (qua bảng booking_promotions)
    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(Promotion::class, 'booking_promotions')
            ->withPivot('promotion_id', 'applied_at')
            ->withTimestamps();
    }

    // Tính lại tổng tiền
    public function recalculateTotal(): void
    {
        $nights = Carbon::parse($this->check_in_date)
            ->diffInDays($this->check_out_date);

        // Tiền phòng
        $roomTotal = $this->room->rate * $nights;

        // Tiền dịch vụ
        $serviceTotal = $this->services->sum(function ($service) {
            return $service->pivot->quantity * $service->price;
        });

        $raw = $roomTotal + $serviceTotal;

        // Tính khuyến mãi nếu có
        $discount = 0;
        $promo = $this->promotions()->latest('pivot_applied_at')->first();

        if ($promo && $promo->isValid()) {
            $discount = $promo->discount_type === 'percent'
                ? $raw * ($promo->discount_value / 100)
                : $promo->discount_value;
        }

        // Cập nhật đơn
        $this->forceFill([
            'raw_total'       => $raw,
            'discount_amount' => $discount,
            'total_amount'    => max(0, $raw - $discount),
        ])->save();
    }
}
