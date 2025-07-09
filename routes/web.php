<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/face-check-in', function () {
    return view('face_checkin');
});
