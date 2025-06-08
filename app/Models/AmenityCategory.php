<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AmenityCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'amenity_categories';

    protected $fillable = [
        'name',
        'description',
    ];

    // Quan hệ 1-n: Một nhóm có nhiều amenity
    public function amenities()
    {
        return $this->hasMany(Amenity::class, 'category_id');
    }
}
