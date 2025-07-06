<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Booking;
use App\Policies\BasePolicy;

class BookingPolicy extends BasePolicy
{
    public function cancel(User $user, Booking $booking)
    {
        return $this->check($user, 'cancel_bookings');
    }
}
