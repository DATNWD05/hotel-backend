<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Customer extends Model
{
    use HasFactory;
    protected $fillable = [
        'cccd',
        'first_name',
        'last_name',
        'gender',
        'email',
        'phone',
        'date_of_birth',
        'nationality',
        'address',
        'note',
    ];
}
