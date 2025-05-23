<?php

use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RoleController;


// Chỉ cho admin được xem danh sách và chi tiết người dùng
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);   
    Route::get('/users/{id}', [UserController::class, 'destroy']);

});

Route::post('/register', [AuthController::class, 'register']);     // 1.1
Route::post('/login', [AuthController::class, 'login']);           // 1.2
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
// Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum', 'role:Admin');

Route::post('/forgot-password', [AuthController::class, 'forgot']); // 1.5
Route::post('/reset-password', [AuthController::class, 'reset']);   // 1.5


Route::middleware(['auth:sanctum','role:Admin'])->group(function () {
    Route::apiResource('role', RoleController::class); // singular path
});


