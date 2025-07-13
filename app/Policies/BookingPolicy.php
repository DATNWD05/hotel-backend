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
    public function checkIn(User $user, Booking $booking)
    {
        return $this->check($user, 'checkin_bookings');
    }

    public function checkOut(User $user, Booking $booking)
    {
        return $this->check($user, 'checkout_bookings');
    }

    public function payDeposit(User $user, Booking $booking)
    {
        return $this->check($user, 'pay_deposit');
    }

    public function addServices(User $user, Booking $booking)
    {
        return $this->check($user, 'add_services');
    }

    public function removeService(User $user, Booking $booking)
    {
        return $this->check($user, 'remove_services');
    }

    public function payByCash(User $user, Booking $booking)
    {
        return $this->check($user, 'pay_by_cash');
    }
}
