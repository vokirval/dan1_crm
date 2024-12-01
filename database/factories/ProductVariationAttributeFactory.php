<?php

namespace Database\Factories;

use App\Models\ProductVariation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductVariationAttribute>
 */
class ProductVariationAttributeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_variation_id' => ProductVariation::factory(), // Создание связанной вариации
            'attribute_name' => $this->faker->randomElement(['color', 'size']),
            'attribute_value' => $this->faker->randomElement(['Red', 'Blue', 'Green', 'S', 'M', 'L']),
        ];
    }
}
