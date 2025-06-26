<?php
// app/Models/RoomPrice.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoomPrice extends Model
{
    use HasFactory;

    // Quan hệ với bảng room_types
    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }
}
