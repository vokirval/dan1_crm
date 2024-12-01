<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductVariation>
 */
class ProductVariationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(), // Связанный продукт
            'sku' => $this->faker->unique()->bothify('VAR-###??'),
            'price' => $this->faker->randomFloat(2, 10, 100),
            'discounted_price' => $this->faker->optional(0.3)->randomFloat(2, 5, 90),
            'cost' => $this->faker->randomFloat(2, 5, 50),
            'stock' => $this->faker->numberBetween(0, 50),
            'status' => $this->faker->randomElement(['active', 'inactive']),
        ];
    }
}
