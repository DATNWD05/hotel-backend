<?php

use App\Http\Controllers\Api\BookingPromotionController;
use App\Http\Controllers\Api\PromotionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;

use App\Http\Controllers\Api\RoleController;

use App\Http\Controllers\Api\UserController;

use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\FloorController;
use App\Http\Controllers\Api\RoomTypeController;

use App\Http\Controllers\Api\CustomerController;

use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\DepartmentController;

use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\ServiceCategoryController;




    
Route::middleware(['auth:sanctum'])->group(function () {
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

    // Routes cho Floor
    Route::apiResource('floors', FloorController::class);

    // Routes cho Room
    Route::apiResource('rooms', RoomController::class);

    // route về quản lí khuyến mãi
    Route::apiResource('promotions', PromotionController::class);
    // Route::apiResource('bookings',   BookingController::class);
    Route::post('bookings/{booking}/apply-promotion',[BookingPromotionController::class, 'apply']);

    // Route Service
    Route::apiResource('service-categories', ServiceCategoryController::class);
    Route::apiResource('service', ServiceController::class);

});


// Khách hàng 
Route::middleware(['auth:sanctum', 'role:Admin,Receptionist'])->group(function () {
    Route::get('/customers', [CustomerController::class, 'index']);
    Route::get('/customers/{id}', [CustomerController::class, 'show']);
    Route::post('/customers', [CustomerController::class, 'store']);
    Route::post('/customers/{id}', [CustomerController::class, 'update']);
});

Route::post('/register', [AuthController::class, 'register']);     // 1.1
Route::post('/login', [AuthController::class, 'login']);           // 1.2
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::post('/forgot-password', [AuthController::class, 'forgot']); // 1.5
Route::post('/reset-password', [AuthController::class, 'reset']);   // 1.5


