<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// **ĐẶT LỊCH CHO COMMAND promotions:sync-status**
Schedule::command('app:sync-promotion-status')
    ->dailyAt("00:00");