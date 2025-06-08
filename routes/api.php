<?php


use App\Http\Controllers\Api\AmenityController;
use App\Http\Controllers\Api\AmenityCategoryController;

use App\Http\Controllers\Api\BookingPromotionController;
use App\Http\Controllers\Api\PromotionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;

use App\Http\Controllers\Api\RoleController;

use App\Http\Controllers\Api\UserController;

use App\Http\Controllers\Api\RoomController;
// use App\Http\Controllers\Api\FloorController;
use App\Http\Controllers\Api\RoomTypeController;

use App\Http\Controllers\Api\CustomerController;

use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\DepartmentController;

use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\ServiceCategoryController;


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
    Route::apiResource('rooms', RoomController::class);
    // Route::post('/rooms/{id}', [RoomController::class, 'update'])->name('rooms.update');


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
});


// Khách hàng
Route::middleware(['auth:sanctum', 'role:1,2'])->group(function () {
    Route::get('/customers', [CustomerController::class, 'index']);
    Route::get('/customers/{id}', [CustomerController::class, 'show']);
    Route::post('/customers', [CustomerController::class, 'store']);
    Route::put('/customers/{id}', [CustomerController::class, 'update']);
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::post('/forgot-password', [AuthController::class, 'forgot']);
Route::post('/reset-password', [AuthController::class, 'reset']);
