<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomType extends Model
{
    /**
     * Các trường có thể được gán giá trị hàng loạt.
     *
     * @var array
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'max_occupancy',
        'base_rate',
        'hourly_rate', // Thêm trường hourly_rate để hỗ trợ đặt phòng theo giờ
    ];

    /**
     * Các trường được tự động cast thành kiểu dữ liệu cụ thể.
     *
     * @var array
     */
    protected $casts = [
        'max_occupancy' => 'integer',
        'base_rate' => 'decimal:2',
        'hourly_rate' => 'decimal:2', // Cast hourly_rate thành kiểu decimal với 2 chữ số thập phân
    ];

    /**
     * Mối quan hệ 1-n: RoomType có nhiều Room.
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * Mối quan hệ n-n: RoomType có nhiều Amenity (qua bảng trung gian room_type_amenities).
     */
    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(
            Amenity::class,
            'room_type_amenities',
            'room_type_id',
            'amenity_id'
        )->withPivot('quantity');
    }
}
