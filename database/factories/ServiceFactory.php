<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition()
    {
        return [
            'name' => $this->faker->word, // Tạo tên dịch vụ ngẫu nhiên
            'category_id' => ServiceCategory::factory(), // Tạo danh mục dịch vụ ngẫu nhiên
            'description' => $this->faker->sentence, // Tạo mô tả dịch vụ ngẫu nhiên
            'price' => $this->faker->randomFloat(2, 50, 1000), // Tạo giá ngẫu nhiên từ 50 đến 1000
        ];
    }
}
