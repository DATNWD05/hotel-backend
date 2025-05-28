<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'category_id', 'description', 'price'];

    public function category()
    {
        return $this->belongsTo(ServiceCategory::class);
    }
}
