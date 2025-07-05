<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class Booking extends Model
{
    protected $fillable = [
        'customer_id',
        'created_by',
        'status',
        'check_in_date',
        'check_out_date',
        'check_in_at',
        'check_out_at',
        'raw_total',
        'discount_amount',
        'total_amount',
        'deposit_amount',
        'is_deposit_paid',
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

    // Quan hệ nhiều phòng
    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class, 'booking_room')
            ->withPivot('rate')
            ->withTimestamps();
    }

    // Dịch vụ đã đặt
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'booking_service')
            ->withPivot(['room_id', 'quantity'])
            ->withTimestamps();
    }


    // Khuyến mãi áp dụng
    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(Promotion::class, 'booking_promotions')
            ->withPivot('promotion_code', 'applied_at')
            ->withTimestamps();
    }

    // Hàm tính lại tổng tiền
    public function recalculateTotal(): void
    {
        // Load các quan hệ cần thiết nếu chưa có
        $this->loadMissing(['rooms.roomType', 'promotions']);

        $nights = Carbon::parse($this->check_in_date)->diffInDays($this->check_out_date);

        // Tính tổng tiền phòng
        $roomTotal = $this->rooms->sum(function ($room) use ($nights) {
            return ($room->roomType->base_rate ?? 0) * $nights;
        });

        // Tính tổng tiền dịch vụ từ bảng trung gian booking_service
        $serviceTotal = DB::table('booking_service')
            ->join('services', 'services.id', '=', 'booking_service.service_id')
            ->where('booking_id', $this->id)
            ->sum(DB::raw('booking_service.quantity * services.price'));

        $raw = $roomTotal + $serviceTotal;

        // Tính khuyến mãi nếu có
        $discount = 0;
        $promo = $this->promotions()->latest('pivot_applied_at')->first();
        if ($promo && $promo->isValid()) {
            $discount = $promo->discount_type === 'percent'
                ? $raw * ($promo->discount_value / 100)
                : $promo->discount_value;
        }

        // Cập nhật lại tổng tiền
        $this->forceFill([
            'raw_total'       => $raw,
            'discount_amount' => $discount,
            'total_amount'    => null,
        ])->save();
    }
}
