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



Route::middleware('auth:sanctum')->group(function () {
    // Chỉ cho admin được xem danh sách và chi tiết người dùng
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);

    Route::get('/me', [UserController::class, 'profile']);

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
    Route::post('/rooms/{room}/restore', [RoomController::class, 'restore']);
    Route::delete('/rooms/{room}/force-delete', [RoomController::class, 'forceDelete']);


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
    // 14. Bảng doanh thu
    Route::get('/statistics/revenue-table', [StatisticsController::class, 'revenueTable']);

    // 15. Dịch vụ đã sử dụng
    Route::get('/statistics/booking-service-table', [StatisticsController::class, 'bookingServiceTable']);

    // 16. Trang thống kê tổng hợp
    Route::get('/statistics/summary-dashboard', [StatisticsController::class, 'summaryDashboard']);

    Route::prefix('payrolls')->middleware('auth:sanctum')->group(function () {
        Route::get('/export-pdf', [PayrollExportController::class, 'exportPdf']);
        Route::get('/export-excel', [PayrollExportController::class, 'exportExcel']);
        Route::get('/', [PayrollController::class, 'index']);
        Route::post('/generate', [PayrollController::class, 'generate']);
        Route::get('/{id}', [PayrollController::class, 'show']);
    });
});

// thanh toán online
Route::post('/vnpay/create-payment', [VNPayController::class, 'create']);
Route::get('/vnpay/return', [VNPayController::class, 'handleReturn']);
// thanh toán online cọc
Route::get('/deposit/vnpay/create', [VNPayController::class, 'payDepositOnline'])->name('deposit.vnpay.create');
Route::get('/deposit/vnpay/return', [VNPayController::class, 'handleDepositReturn'])->name('vnpay.deposit.return');


// Khách hàng
Route::middleware(['auth:sanctum', 'role:1,2,3'])->group(function () {
    Route::get('/customers', [CustomerController::class, 'index']);
    Route::get('/customers/{customer}', [CustomerController::class, 'show']);
    Route::post('/customers', [CustomerController::class, 'store']);
    Route::put('/customers/{customer}', [CustomerController::class, 'update']);
    Route::get('/customers/check-cccd/{cccd}', [CustomerController::class, 'checkCccd']);
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::post('/forgot-password', [AuthController::class, 'forgot']);
Route::post('/reset-password', [AuthController::class, 'reset']);

// Xử lý Bookings
Route::middleware(['auth:sanctum', "role:1,2,3"])->group(function () {
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings/{booking}', [BookingController::class, 'show']);
    Route::put('/bookings/{booking}', [BookingController::class, 'update']);
    Route::post('/bookings/{booking}/add-services', [BookingController::class, 'addServices']);
    Route::post('/bookings/{booking}/deposit', [BookingController::class, 'payDeposit']);


    // xử lí trạng thái bookings
    Route::get('/check-in/{booking}', [BookingController::class, 'showCheckInInfo']);
    Route::post('/check-in/{booking}', [BookingController::class, 'checkIn']);
    Route::get('/check-out/{booking}', [BookingController::class, 'checkOut']);
    Route::post('/pay-cash/{booking}', [BookingController::class, 'payByCash']);
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);

    Route::post('/bookings/{booking}/remove-service', [BookingController::class, 'removeService']);

    // Hóa đơn
    Route::prefix('invoices')->group(function () {

        // Xem danh sách hóa đơn (tùy chọn)
        Route::get('/', [InvoiceController::class, 'index']);

        // Xem chi tiết 1 hóa đơn
        Route::get('/{id}', [InvoiceController::class, 'show']);

        // (Tùy chọn) Xuất PDF hóa đơn
        Route::get('/booking/{booking_id}/print', [InvoiceController::class, 'printInvoice']);
    });
});

// Phân quyền
Route::middleware('auth:sanctum')->group(function () {
    // Resource routes: index, store, show, update, destroy
    Route::apiResource('roles', RoleController::class);

    // Gán / gỡ quyền
    Route::post('roles/{role}/permissions', [RoleController::class, 'assignPermissions']);
    Route::delete('roles/{role}/permissions', [RoleController::class, 'removePermissions']);

    // Lấy danh sách quyền
    Route::get('/permissions', [PermissionController::class, 'index']);
});

// Routes cho châm công
Route::get('/attendances', [AttendanceController::class, 'index']);
Route::post('/faceAttendance', [AttendanceController::class, 'faceAttendance']);
// Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn']);
// Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut']);

// Thêm danh tính
Route::post('/employees/{employee}/upload-faces', [EmployeeController::class, 'uploadFaces']);


// Routes cho quản lý ca làm việc
Route::prefix('work-assignments')->group(function () {
    Route::get('/', [WorkAssignmentController::class, 'index']);
    Route::post('/', [WorkAssignmentController::class, 'store']);
    Route::put('/{workAssignment}', [WorkAssignmentController::class, 'update']);
    Route::delete('/{workAssignment}', [WorkAssignmentController::class, 'destroy']);
    Route::post('/import', [WorkAssignmentController::class, 'import']);
});

Route::prefix('shifts')->group(function () {
    Route::get('/', [ShiftController::class, 'index']);
    Route::post('/', [ShiftController::class, 'store']);
    Route::get('/{shift}', [ShiftController::class, 'show']);
    Route::put('/{shift}', [ShiftController::class, 'update']);
    Route::delete('/{shift}', [ShiftController::class, 'destroy']);
});
