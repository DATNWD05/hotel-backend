<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Amenity extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'amenities';

    protected $fillable = [
        'category_id',
        'code',
        'name',
        'description',
        'icon',
        'price',
        'default_quantity',
        'status',
    ];

    protected $casts = [
        'price'            => 'decimal:2',
        'default_quantity' => 'integer',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
    ];

    // Quan hệ ngược belongsTo → AmenityCategory
    public function category()
    {
        return $this->belongsTo(AmenityCategory::class, 'category_id');
    }

    // Quan hệ nhiều-nhiều với RoomType, kèm cột pivot 'quantity'
    public function roomTypes()
    {
        return $this->belongsToMany(
            RoomType::class,
            'room_type_amenities',
            'amenity_id',
            'room_type_id'
        )->withPivot('quantity');
    }
}
