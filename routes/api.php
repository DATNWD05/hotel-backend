<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\FloorController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\RoomTypeController;
use App\Http\Controllers\Api\DepartmentController;



Route::middleware(['auth:sanctum', 'role:Admin'])->group(function () {
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
// Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum', 'role:Admin');

Route::post('/forgot-password', [AuthController::class, 'forgot']); // 1.5
Route::post('/reset-password', [AuthController::class, 'reset']);   // 1.5


Route::middleware(['auth:sanctum', 'role:Admin'])->group(function () {
    // Routes cho RoomType
    Route::get('room-types', [RoomTypeController::class, 'index']);
    Route::get('room-types/{id}', [RoomTypeController::class, 'show']);
    Route::post('room-types', [RoomTypeController::class, 'store']);
    Route::put('room-types/{id}', [RoomTypeController::class, 'update']);
    Route::delete('room-types/{id}', [RoomTypeController::class, 'destroy']);

    // Routes cho Floor
    Route::get('floors', [FloorController::class, 'index']);
    Route::get('floors/{id}', [FloorController::class, 'show']);
    Route::post('floors', [FloorController::class, 'store']);
    Route::put('floors/{id}', [FloorController::class, 'update']);
    Route::delete('floors/{id}', [FloorController::class, 'destroy']);

    // Routes cho Room
    Route::get('rooms', [RoomController::class, 'index']);
    Route::get('rooms/{id}', [RoomController::class, 'show']);
    Route::post('rooms', [RoomController::class, 'store']);
    Route::put('rooms/{id}', [RoomController::class, 'update']);
    Route::delete('rooms/{id}', [RoomController::class, 'destroy']);
    Route::get('rooms/floor/{floorId}', [RoomController::class, 'getRoomsByFloor']);
});
