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
        'is_hourly',
    ];

    /**
     * Quan hệ với khách hàng
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Quan hệ với nhân viên tạo đơn
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Quan hệ với nhiều phòng
     */
    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class, 'booking_room')
            ->withPivot('rate')
            ->withTimestamps();
    }

    /**
     * Quan hệ với dịch vụ đã đặt
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'booking_service')
            ->withPivot(['room_id', 'quantity'])
            ->withTimestamps();
    }

    /**
     * Quan hệ với khuyến mãi áp dụng
     */
    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(Promotion::class, 'booking_promotions')
            ->withPivot('promotion_code', 'applied_at')
            ->withTimestamps();
    }

    /**
     * Tính lại tổng tiền của đơn đặt phòng
     */
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

            // Tính thời gian lưu trú (theo giờ hoặc ngày)
            $duration = $this->is_hourly
                ? max(1, ceil($checkIn->diffInMinutes($checkOut) / 60)) // Tính theo giờ
                : max(1, $checkIn->diffInDays($checkOut)); // Tính theo ngày

            if ($duration <= 0) {
                Log::warning('Booking ID: ' . $this->id . ' - Invalid date range: ' . $checkIn->toDateString() . ' to ' . $checkOut->toDateString());
                return;
            }

            // Tính tổng tiền phòng
            $roomTotal = 0;
            if ($this->rooms->isNotEmpty()) {
                $roomTotal = $this->rooms->sum(function ($room) use ($duration) {
                    $rate = $this->is_hourly
                        ? ($room->roomType?->hourly_rate ?? 0)
                        : ($room->roomType?->base_rate ?? 0);
                    if ($rate <= 0) {
                        Log::warning('Booking ID: ' . $this->id . ' - Invalid rate for room ID: ' . $room->id);
                    }
                    return $rate * $duration;
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

            $rawTotal = $roomTotal + $serviceTotal;

            // Tính khuyến mãi nếu có
            $discount = 0;
            $promo = $this->promotions()->latest('pivot_applied_at')->first();
            if ($promo && method_exists($promo, 'isValid') && $promo->isValid()) {
                $discount = $promo->discount_type === 'percent'
                    ? min($rawTotal * ($promo->discount_value / 100), $rawTotal) // Đảm bảo discount không vượt raw
                    : min($promo->discount_value, $rawTotal);
            }

            // Cập nhật lại tổng tiền
            $this->forceFill([
                'raw_total' => $rawTotal,
                'discount_amount' => $discount,
                'total_amount' => max(0, $rawTotal - $discount), // Đảm bảo total_amount không âm
            ])->save();

            Log::info('Booking ID: ' . $this->id . ' - Recalculated totals: raw_total=' . $rawTotal . ', discount_amount=' . $discount . ', total_amount=' . max(0, $rawTotal - $discount));
        } catch (\Exception $e) {
            Log::error('Error in recalculateTotal for Booking ID: ' . $this->id . ' - ' . $e->getMessage());
        }
    }
}
