<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ShiftSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('shifts')->insert([
            [
                'name' => 'Ca sáng',
                'start_time' => '06:00:00',
                'end_time' => '14:00:00',
                'hourly_rate' => 25000,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Ca chiều',
                'start_time' => '14:00:00',
                'end_time' => '22:00:00',
                'hourly_rate' => 27000,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Ca tối',
                'start_time' => '22:00:00',
                'end_time' => '06:00:00', // qua ngày sau
                'hourly_rate' => 30000,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);
    }
}
