<?php

namespace Database\Factories;

use App\Models\OrderStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderStatusFactory extends Factory
{
    protected $model = OrderStatus::class;

    public function definition()
    {
        return [
            'name' => $this->faker->randomElement(['Pending', 'Processing', 'Completed', 'Cancelled']),
            'color' => ltrim($this->faker->hexColor(), '#'),
        ];
    }
}
