<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\WorkAssignment;

class WorkAssignmentSeeder extends Seeder
{
    public function run()
    {
        WorkAssignment::insert([
            [
                'employee_id' => 21,
                'shift_id' => 1, // Ca sáng
                'work_date' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
