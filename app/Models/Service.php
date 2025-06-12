<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Service extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'category_id', 'description', 'price'];

    public function category()
    {
        return $this->belongsTo(ServiceCategory::class);
    }

    // Dịch vụ được dùng trong nhiều booking
    public function bookings(): BelongsToMany
    {
        return $this->belongsToMany(Booking::class, 'booking_service')
            ->withPivot('quantity')
            ->withTimestamps();
    }
}
