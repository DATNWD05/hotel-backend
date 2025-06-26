<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomType extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'max_occupancy',
        'base_rate',
        'created_at',
    ];

    /**
     * Mối quan hệ 1-n: RoomType có nhiều Room
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * Mối quan hệ n-n: RoomType có nhiều Amenity (qua bảng trung gian room_type_amenities)
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

     // Quan hệ với bảng room_prices
    public function roomPrices()
    {
        return $this->hasMany(RoomPrice::class);
    }
}
