<?php

namespace Database\Seeders;

use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;

class ServiceCategorySeeder extends Seeder
{
    public function run()
    {
        // Tạo 3 danh mục dịch vụ cụ thể
        ServiceCategory::create([
            'name' => 'Ăn uống',
            'description' => 'Các dịch vụ liên quan đến ăn uống trong khách sạn.'
        ]);

        ServiceCategory::create([
            'name' => 'Vận chuyển',
            'description' => 'Các dịch vụ vận chuyển, taxi, đưa đón khách.'
        ]);

        ServiceCategory::create([
            'name' => 'Giải trí',
            'description' => 'Các dịch vụ giải trí, thể thao, spa.'
        ]);
    }
}
