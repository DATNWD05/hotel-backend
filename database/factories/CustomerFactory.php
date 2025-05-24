<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cccd'          => $this->faker->unique()->numerify('############'),
            'first_name'    => $this->faker->firstName,
            'last_name'     => $this->faker->lastName,
            'gender'        => $this->faker->randomElement(['male', 'female', 'other']), // ✅ giới tính
            'email'         => $this->faker->unique()->safeEmail,
            'phone'         => $this->faker->phoneNumber,
            'date_of_birth' => $this->faker->date('Y-m-d', '2005-01-01'),
            'nationality'   => 'Vietnamese',
            'address'       => $this->faker->address,
            'note'          => $this->faker->sentence, // ✅ ghi chú ngắn gọn
        ];
    }
}
