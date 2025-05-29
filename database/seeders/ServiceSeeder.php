<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run()
    {
        // Lấy các danh mục dịch vụ đã tạo
        $categoryFood = ServiceCategory::where('name', 'Ăn uống')->first();
        $categoryTransport = ServiceCategory::where('name', 'Vận chuyển')->first();
        $categoryEntertainment = ServiceCategory::where('name', 'Giải trí')->first();

        // Tạo 5 dịch vụ cho danh mục "Ăn uống"
        Service::factory()->count(5)->create([
            'category_id' => $categoryFood->id
        ]);

        // Tạo 5 dịch vụ cho danh mục "Vận chuyển"
        Service::factory()->count(5)->create([
            'category_id' => $categoryTransport->id
        ]);

        // Tạo 5 dịch vụ cho danh mục "Giải trí"
        Service::factory()->count(5)->create([
            'category_id' => $categoryEntertainment->id
        ]);
    }
}
