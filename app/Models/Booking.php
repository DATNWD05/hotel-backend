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
        'deposit_amount',
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

    // Dịch vụ đã đặt
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'booking_service')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    // Khuyến mãi áp dụng
    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(Promotion::class, 'booking_promotions')
            ->withPivot('promotion_code', 'applied_at') // sửa đúng pivot
            ->withTimestamps();
    }

    // Tính lại tổng
    public function recalculateTotal(): void
    {
        $nights = Carbon::parse($this->check_in_date)->diffInDays($this->check_out_date);

        // Giá phòng lấy từ roomType
        $roomTypePrice = $this->room->roomType->base_rate ?? 0;
        $roomTotal = $roomTypePrice * $nights;

        // Dịch vụ
        $serviceTotal = $this->services->sum(function ($service) {
            return $service->pivot->quantity * $service->base_rate;
        });

        $raw = $roomTotal + $serviceTotal;

        // Tính giảm giá
        $discount = 0;
        $promo = $this->promotions()->latest('pivot_applied_at')->first();
        if ($promo && $promo->isValid()) {
            $discount = $promo->discount_type === 'percent'
                ? $raw * ($promo->discount_value / 100)
                : $promo->discount_value;
        }

        // Cập nhật lại
        $this->forceFill([
            'raw_total'       => $raw,
            'discount_amount' => $discount,
            'total_amount'    => max(0, $raw - $discount),
        ])->save();
    }
}
