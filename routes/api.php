<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\AmenityCategoryController;
use App\Http\Controllers\Api\AmenityController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\BookingPromotionController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\RoomTypeController;
use App\Http\Controllers\Api\ServiceCategoryController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\StatisticsController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VNPayController;
use App\Http\Controllers\Api\WorkAssignmentController;
use App\Http\Controllers\Api\PayrollController;
use App\Http\Controllers\Api\PayrollExportController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\OvertimeRequestController;

Route::middleware('auth:sanctum', 'role:1,2')->group(function () {
    // Quản lý người dùng
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);

    Route::get('/me', [UserController::class, 'profile']);

    // Quản lý vai trò
    Route::apiResource('role', RoleController::class); // singular path

    // Quản lý nhân viên và phòng ban
    Route::apiResource('employees', EmployeeController::class);
    Route::apiResource('departments', DepartmentController::class);

    // Quản lý loại phòng
    Route::apiResource('room-types', RoomTypeController::class);

    // Quản lý phòng
    Route::get('/rooms/trashed', [RoomController::class, 'trashed']);
    Route::apiResource('rooms', RoomController::class);
    Route::post('/rooms/{room}/restore', [RoomController::class, 'restore']);
    Route::delete('/rooms/{room}/force-delete', [RoomController::class, 'forceDelete']);
    // Lấy danh sách phòng khả dụng
    Route::get('/available-rooms', [RoomController::class, 'getAvailableRooms']);

    // Quản lý khuyến mãi
    Route::apiResource('promotions', PromotionController::class);
    Route::post('bookings/{booking}/apply-promotion', [BookingPromotionController::class, 'apply']);

    // Quản lý danh mục dịch vụ
    Route::apiResource('service-categories', ServiceCategoryController::class);

    // Quản lý dịch vụ
    Route::apiResource('service', ServiceController::class);

    // Quản lý danh mục tiện nghi
    Route::prefix('amenity-categories')->group(function () {
        Route::get('trashed', [AmenityCategoryController::class, 'trashed'])
            ->name('amenity-categories.trashed');
        Route::post('{id}/restore', [AmenityCategoryController::class, 'restore'])
            ->whereNumber('id')
            ->name('amenity-categories.restore');
        Route::delete('{id}/force', [AmenityCategoryController::class, 'forceDelete'])
            ->whereNumber('id')
            ->name('amenity-categories.forceDelete');
    });
    Route::apiResource('amenity-categories', AmenityCategoryController::class);

    // Quản lý tiện nghi
    Route::apiResource('amenities', AmenityController::class);

    // Đồng bộ tiện nghi với loại phòng
    Route::put('room-types/{room_type}/amenities', [RoomTypeController::class, 'syncAmenities']);

    // Thống kê
    Route::get('/statistics/revenue-table', [StatisticsController::class, 'revenueTable']);
    Route::get('/statistics/booking-service-table', [StatisticsController::class, 'bookingServiceTable']);
    Route::get('/statistics/summary-dashboard', [StatisticsController::class, 'summaryDashboard']);

    // Quản lý bảng lương
    Route::prefix('payrolls')->group(function () {
        Route::get('/export-pdf', [PayrollExportController::class, 'exportPdf']);
        Route::get('/export-excel', [PayrollExportController::class, 'exportExcel']);
        Route::get('/', [PayrollController::class, 'index']);
        Route::post('/generate', [PayrollController::class, 'generate']);
        Route::get('/{id}', [PayrollController::class, 'show']);
    });

    // Quản lý phân công ca làm việc
    Route::prefix('work-assignments')->group(function () {
        Route::get('/', [WorkAssignmentController::class, 'index']);
        Route::post('/', [WorkAssignmentController::class, 'store']);
        Route::post('/import', [WorkAssignmentController::class, 'import']);
    });

    // Quản lý ca làm việc
    Route::prefix('shifts')->group(function () {
        Route::get('/', [ShiftController::class, 'index']);
        Route::post('/', [ShiftController::class, 'store']);
        Route::get('/{shift}', [ShiftController::class, 'show']);
        Route::put('/{shift}', [ShiftController::class, 'update']);
        Route::delete('/{shift}', [ShiftController::class, 'destroy']);
    });

    // Quản lý yêu cầu tăng ca
    Route::prefix('overtime-requests')->group(function () {
        Route::get('/', [OvertimeRequestController::class, 'index']);
        Route::post('/', [OvertimeRequestController::class, 'store']);
    });
});

// Xử lý đặt phòng và khách hàng
Route::middleware(['auth:sanctum', 'role:1,2,3'])->group(function () {
    // Quản lý đặt phòng
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings/{booking}', [BookingController::class, 'show']);
    Route::put('/bookings/{booking}', [BookingController::class, 'update']);
    Route::post('/bookings/{booking}/add-services', [BookingController::class, 'addServices']);
    Route::post('/bookings/{booking}/deposit', [BookingController::class, 'payDeposit']);
    Route::get('/check-in/{booking}', [BookingController::class, 'showCheckInInfo']);
    Route::post('/check-in/{booking}', [BookingController::class, 'checkIn']);
    Route::get('/check-out/{booking}', [BookingController::class, 'checkOut']);
    Route::post('/pay-cash/{booking}', [BookingController::class, 'payByCash']);
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);
    Route::post('/bookings/{booking}/remove-service', [BookingController::class, 'removeService']);
    // Xác thực phòng trước khi đặt
    Route::post('/bookings/validate-rooms', [BookingController::class, 'validateRooms']);

    // Quản lý khách hàng
    Route::get('/customers', [CustomerController::class, 'index']);
    Route::get('/customers/{customer}', [CustomerController::class, 'show']);
    Route::post('/customers', [CustomerController::class, 'store']);
    Route::put('/customers/{customer}', [CustomerController::class, 'update']);
    Route::get('/customers/check-cccd/{cccd}', [CustomerController::class, 'checkCccd']);

    // Quản lý hóa đơn
    Route::prefix('invoices')->group(function () {
        Route::get('/', [InvoiceController::class, 'index']);
        Route::get('/{id}', [InvoiceController::class, 'show']);
        Route::get('/booking/{booking_id}/print', [InvoiceController::class, 'printInvoice']);
    });
});

// Quản lý phân quyền
Route::middleware('auth:sanctum', 'role:1')->group(function () {
    Route::apiResource('roles', RoleController::class);
    Route::post('roles/{role}/permissions', [RoleController::class, 'assignPermissions']);
    Route::delete('roles/{role}/permissions', [RoleController::class, 'removePermissions']);
    Route::get('/permissions', [PermissionController::class, 'index']);
});

// Quản lý đăng nhập và xác thực
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::post('/forgot-password', [AuthController::class, 'forgot']);
Route::post('/reset-password', [AuthController::class, 'reset']);

// Quản lý chấm công
Route::get('/attendances', [AttendanceController::class, 'index']);
Route::post('/faceAttendance', [AttendanceController::class, 'faceAttendance']);

// Thêm danh tính cho nhân viên
Route::post('/employees/{manv}/upload-faces', [EmployeeController::class, 'uploadFaces']);

// Thanh toán online
Route::post('/vnpay/create-payment', [VNPayController::class, 'create']);
Route::get('/vnpay/return', [VNPayController::class, 'handleReturn']);
Route::get('/deposit/vnpay/create', [VNPayController::class, 'payDepositOnline'])->name('deposit.vnpay.create');
Route::get('/deposit/vnpay/return', [VNPayController::class, 'handleDepositReturn'])->name('vnpay.deposit.return');
