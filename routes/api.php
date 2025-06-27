<?php

use App\Http\Controllers\Api\VNPayController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\RoomController;

use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AmenityController;
use App\Http\Controllers\Api\BookingController;

use App\Http\Controllers\Api\InvoiceController;

use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\CustomerController;

use App\Http\Controllers\Api\EmployeeController;

use App\Http\Controllers\Api\RoomTypeController;
use App\Http\Controllers\Api\PromotionController;

use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\AmenityCategoryController;
use App\Http\Controllers\Api\ServiceCategoryController;
use App\Http\Controllers\Api\BookingPromotionController;

use App\Http\Controllers\Api\StatisticsController;

Route::middleware(['auth:sanctum', "role:1"])->group(function () {
    // Chỉ cho admin được xem danh sách và chi tiết người dùng
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::post('/users/{id}', [UserController::class, 'show']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);

    // Route Role
    Route::apiResource('role', RoleController::class); // singular path

    //route về chức năng quản lí nhân viên và phòng ban
    Route::apiResource('employees', EmployeeController::class);
    Route::apiResource('departments', DepartmentController::class);

    // Routes cho RoomType
    Route::apiResource('room-types', RoomTypeController::class);

    // Routes cho Room
    Route::get('/rooms/trashed', [RoomController::class, 'trashed']);
    Route::apiResource('rooms', RoomController::class);
    // Route::post('/rooms/{id}', [RoomController::class, 'update'])->name('rooms.update');
    Route::post('/rooms/{id}/restore', [RoomController::class, 'restore']);
    Route::delete('/rooms/{id}/force-delete', [RoomController::class, 'forceDelete']);


    // route về quản lí khuyến mãi
    Route::apiResource('promotions', PromotionController::class);
    // Route::apiResource('bookings',   BookingController::class);
    Route::post('bookings/{booking}/apply-promotion', [BookingPromotionController::class, 'apply']);

    // Route Service
    Route::apiResource('service-categories', ServiceCategoryController::class);
    // Route Danh muc Service
    Route::apiResource('service', ServiceController::class);

    // Nhóm tiện nghi
    Route::prefix('amenity-categories')->group(function () {


        // Chỉ hiển thị các bản ghi đã bị xóa mềm soft-deleted
        Route::get('trashed', [AmenityCategoryController::class, 'trashed'])
            ->name('amenity-categories.trashed');

        // Restore 1 bản ghi
        Route::post('{id}/restore', [AmenityCategoryController::class, 'restore'])
            ->whereNumber('id')
            ->name('amenity-categories.restore');

        // xóa hoàn toàn bản ghi khỏi cơ sở dữ liệu Force-delete (xóa hẳn)
        Route::delete('{id}/force', [AmenityCategoryController::class, 'forceDelete'])
            ->whereNumber('id')
            ->name('amenity-categories.forceDelete');
    });
    // Crud cơ bản
    Route::apiResource('amenity-categories', AmenityCategoryController::class);

    // CRUD tiện nghi
    Route::apiResource('amenities', AmenityController::class);

    // Sync amenities kèm quantity
    Route::put(
        'room-types/{room_type}/amenities',
        [RoomTypeController::class, 'syncAmenities']
    );

    // Thống kê
    Route::prefix('statistics')->group(function () {
        // 1. Tổng doanh thu toàn hệ thống
        Route::get('/total-revenue', [StatisticsController::class, 'totalRevenue']);

        // 2. Doanh thu từng ngày
        Route::get('/revenue-by-day', [StatisticsController::class, 'revenueByDay']);

        // 3. Tổng chi phí từng booking
        Route::get('/total-per-booking', [StatisticsController::class, 'totalPerBooking']);

        // 4. Doanh thu theo khách hàng
        Route::get('/revenue-by-customer', [StatisticsController::class, 'revenueByCustomer']);

        // 5. Doanh thu theo phòng
        Route::get('/revenue-by-room', [StatisticsController::class, 'revenueByRoom']);

        // 6. Tỷ lệ lấp đầy phòng
        Route::get('/occupancy-rate', [StatisticsController::class, 'occupancyRate']);

        // 7. Trung bình thời gian lưu trú
        Route::get('/average-stay-duration', [StatisticsController::class, 'averageStayDuration']);

        // 8. Tỷ lệ huỷ phòng
        Route::get('/cancellation-rate', [StatisticsController::class, 'cancellationRate']);

        // 9. Top khách đặt nhiều nhất
        Route::get('/top-customers', [StatisticsController::class, 'topFrequentCustomers']);

        // 10. Tổng số booking theo tháng
        Route::get('/bookings-by-month', [StatisticsController::class, 'bookingsByMonth']);

        // 11. Doanh thu theo loại phòng
        Route::get('/revenue-by-room-type', [StatisticsController::class, 'revenueByRoomType']);

        // 12. Tổng doanh thu từ dịch vụ
        Route::get('/total-service-revenue', [StatisticsController::class, 'totalServiceRevenue']);

        // 13. Số lượng phòng được đặt theo loại
        Route::get('/room-type-booking-count', [StatisticsController::class, 'roomTypeBookingCount']);
    });
});


// Khách hàng
Route::middleware(['auth:sanctum', 'role:1,2'])->group(function () {
    Route::get('/customers', [CustomerController::class, 'index']);
    Route::get('/customers/{id}', [CustomerController::class, 'show']);
    Route::post('/customers', [CustomerController::class, 'store']);
    Route::put('/customers/{id}', [CustomerController::class, 'update']);
    Route::get('/customers/check-cccd/{cccd}', [CustomerController::class, 'checkCccd']);
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::post('/forgot-password', [AuthController::class, 'forgot']);
Route::post('/reset-password', [AuthController::class, 'reset']);

Route::middleware(['auth:sanctum', "role:1,2"])->group(function () {
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings/{id}', [BookingController::class, 'show']);
    Route::put('/bookings/{id}', [BookingController::class, 'update']);
    Route::post('/bookings/{id}/add-services', [BookingController::class, 'addServices']);
    // checkin
    Route::get('/check-in/{id}', [BookingController::class, 'showCheckInInfo']);
    Route::post('/check-in/{id}', [BookingController::class, 'checkIn']);


    // Hóa đơn
    Route::get('/invoice/by-customer/{customer_id}', [InvoiceController::class, 'generateGroupInvoice']);
});




Route::post('/vnpay/create-payment', [VNPayController::class, 'create']);
Route::get('/vnpay/return', [VNPayController::class, 'handleReturn']);
