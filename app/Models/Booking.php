<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        try {
            // Load các quan hệ cần thiết nếu chưa có
            $this->loadMissing(['rooms.roomType', 'promotions']);

            // Kiểm tra ngày check-in và check-out hợp lệ
            if (!$this->check_in_date || !$this->check_out_date) {
                Log::warning('Booking ID: ' . $this->id . ' - Missing check-in or check-out date');
                return;
            }

            $checkIn = Carbon::parse($this->check_in_date);
            $checkOut = Carbon::parse($this->check_out_date);
            $nights = $checkIn->diffInDays($checkOut);

            if ($nights <= 0) {
                Log::warning('Booking ID: ' . $this->id . ' - Invalid date range: ' . $checkIn->toDateString() . ' to ' . $checkOut->toDateString());
                return;
            }

            // Tính tổng tiền phòng
            $roomTotal = 0;
            if ($this->rooms->isNotEmpty()) {
                $roomTotal = $this->rooms->sum(function ($room) use ($nights) {
                    $baseRate = $room->roomType?->base_rate ?? 0;
                    if ($baseRate <= 0) {
                        Log::warning('Booking ID: ' . $this->id . ' - Invalid base_rate for room ID: ' . $room->id);
                    }
                    return $baseRate * $nights;
                });
            }

            // Tính tổng tiền dịch vụ từ bảng trung gian booking_service
            $serviceTotal = 0;
            $serviceQuery = DB::table('booking_service')
                ->join('services', 'services.id', '=', 'booking_service.service_id')
                ->where('booking_id', $this->id);
            if ($serviceQuery->exists()) {
                $serviceTotal = $serviceQuery->sum(DB::raw('booking_service.quantity * services.price'));
            }

            $raw = $roomTotal + $serviceTotal;

            // Tính khuyến mãi nếu có
            $discount = 0;
            $promo = $this->promotions()->latest('pivot_applied_at')->first();
            if ($promo && method_exists($promo, 'isValid') && $promo->isValid()) {
                $discount = $promo->discount_type === 'percent'
                    ? min($raw * ($promo->discount_value / 100), $raw) // Đảm bảo discount không vượt raw
                    : min($promo->discount_value, $raw);
            }

            // Cập nhật lại tổng tiền
            $this->forceFill([
                'raw_total' => $raw,
                'discount_amount' => $discount,
                'total_amount' => max(0, $raw - $discount), // Đảm bảo total_amount không âm
            ])->save();
        } catch (\Exception $e) {
            Log::error('Error in recalculateTotal for Booking ID: ' . $this->id . ' - ' . $e->getMessage());
        }
    }
}
