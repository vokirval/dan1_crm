<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'sku' => $this->faker->unique()->bothify('PROD-###??'),
            'description' => $this->faker->paragraph,
            'type' => $this->faker->randomElement(['simple', 'variable']),
            'price' => $this->faker->randomFloat(2, 10, 100),
            'discounted_price' => $this->faker->optional(0.3)->randomFloat(2, 5, 90),
            'cost' => $this->faker->randomFloat(2, 5, 50),
            'stock' => $this->faker->numberBetween(0, 100),
            'status' => $this->faker->randomElement(['active', 'inactive']),
            'category_id' => null, // Связь может быть настроена позже
        ];
    }
}
