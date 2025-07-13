<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SalaryRule;

class SalaryRuleSeeder extends Seeder
{
    public function run(): void
    {
        SalaryRule::insert([
            [
                'role_id' => 1, // Owner
                'overtime_multiplier' => 0,
                'late_penalty_per_minute' => 0,
                'early_leave_penalty_per_minute' => 0,
                'daily_allowance' => 0,
            ],
            [
                'role_id' => 2, // Manager
                'overtime_multiplier' => 1.5,
                'late_penalty_per_minute' => 500,
                'early_leave_penalty_per_minute' => 500,
                'daily_allowance' => 50000,
            ],
            [
                'role_id' => 3, // Lễ tân
                'overtime_multiplier' => 1.25,
                'late_penalty_per_minute' => 1000,
                'early_leave_penalty_per_minute' => 1000,
                'daily_allowance' => 20000,
            ],
        ]);
    }
}
