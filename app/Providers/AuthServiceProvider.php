<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

// Models
use App\Models\Role;
use App\Models\User;
use App\Models\Booking;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Promotion;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Amenity;
use App\Models\Customer;

// Policies
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use App\Policies\BookingPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\InvoicePolicy;
use App\Policies\PromotionPolicy;
use App\Policies\RoomPolicy;
use App\Policies\RoomTypePolicy;
use App\Policies\ServicePolicy;
use App\Policies\ServiceCategoryPolicy;
use App\Policies\AmenityPolicy;
use App\Policies\CustomerPolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Amenity::class => AmenityPolicy::class,
        Role::class => RolePolicy::class,
        User::class => UserPolicy::class,
        Booking::class => BookingPolicy::class,
        Employee::class => EmployeePolicy::class,
        Invoice::class => InvoicePolicy::class,
        Promotion::class => PromotionPolicy::class,
        Room::class => RoomPolicy::class,
        RoomType::class => RoomTypePolicy::class,
        Service::class => ServicePolicy::class,
        ServiceCategory::class => ServiceCategoryPolicy::class,
        Amenity::class => AmenityPolicy::class,
        Customer::class => CustomerPolicy::class,
    ];

    public function boot()
    {
        $this->registerPolicies();
    }
}
