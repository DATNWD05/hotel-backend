<?php

namespace Database\Seeders;

use App\Models\Customer;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
use Database\Seeders\ServiceCategorySeeder;
use Database\Seeders\ServiceSeeder;
use Database\Seeders\ShiftSeeder;
use Illuminate\Database\Seeder;
use Database\Seeders\WorkAssignmentSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Vũ Thành Long',
        //     'email' => 'vuthanhlong@gmail.com',
        //     'password' => bcrypt('Long12345'),
        //     'role_id' => 1,
        // ]);

        // Customer::factory()->count(5)->create();
        // $this->call([
        //     ServiceCategorySeeder::class, // Seeder cho các danh mục dịch vụ
        //     ServiceSeeder::class,         // Seeder cho các dịch vụ
        // ]);

        // $this->call(ShiftSeeder::class);
        // $this->call(WorkAssignmentSeeder::class);
        $this->call([
            ShiftSeeder::class
        ]);
    }
}
