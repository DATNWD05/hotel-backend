<?php

namespace App\Policies;

class BookingPolicy extends BasePolicy
{
    public function cancel($user, $booking)
    {
        return $this->check($user, 'cancel_bookings');
    }
}
