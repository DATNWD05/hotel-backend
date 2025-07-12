<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/face-check-in', function () {
    return view('facecheckin.face_checkin');
});

Route::get('/upload-faces', function () {
    return view('employees.upload-face');
});
