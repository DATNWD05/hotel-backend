<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use App\Models\RoomType;
use App\Models\Booking;

class Room extends Model
{
    use SoftDeletes;

    protected $fillable = ['room_number', 'room_type_id', 'status', 'image', 'deleted_at'];
    protected $dates = ['deleted_at'];

    /**
     * Quan hệ với loại phòng
     */
    public function roomType()
    {
        return $this->belongsTo(RoomType::class)->withDefault([
            'base_rate' => 0, // Giá mặc định nếu loại phòng không tồn tại
            'max_occupancy' => 0,
        ]);
    }

    /**
     * Quan hệ many-to-many với booking thông qua bảng booking_room
     */
    public function bookings()
    {
        return $this->belongsToMany(Booking::class, 'booking_room', 'room_id', 'booking_id')
            ->withTimestamps() // Nếu bảng trung gian có created_at và updated_at
            ->withPivot(['rate', 'created_at', 'updated_at']); // Thêm các cột pivot nếu cần
    }

    /**
     * Kiểm tra xem phòng có sẵn trong khoảng thời gian cụ thể
     */
    public function isAvailable(string $checkInDate, string $checkOutDate): bool
    {
        try {
            // Kiểm tra nếu phòng đã bị xóa mềm
            if ($this->trashed()) {
                return false;
            }

            // Lấy các booking liên quan đến phòng này
            $bookings = $this->bookings()
                ->whereIn('status', ['Pending', 'Confirmed', 'Checked-in'])
                ->where(function ($query) use ($checkInDate, $checkOutDate) {
                    $query->whereBetween('check_in_date', [$checkInDate, $checkOutDate])
                        ->orWhereBetween('check_out_date', [$checkInDate, $checkOutDate])
                        ->orWhere(function ($q) use ($checkInDate, $checkOutDate) {
                            $q->where('check_in_date', '<=', $checkInDate)
                                ->where('check_out_date', '>=', $checkOutDate);
                        });
                })
                ->exists();

            return !$bookings;
        } catch (\Exception $e) {
            Log::error('Error checking availability for Room ID: ' . $this->id . ' - ' . $e->getMessage());
            return false; // Trả về false nếu có lỗi để an toàn
        }
    }
}
