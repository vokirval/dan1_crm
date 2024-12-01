<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;
use App\Models\ProductVariation;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition()
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => null, // Устанавливается в сидере
            'product_variation_id' => null, // Устанавливается в сидере
            'quantity' => $this->faker->numberBetween(1, 10),
            'price' => $this->faker->randomFloat(2, 10, 100),
            'subtotal' => function (array $attributes) {
                return $attributes['quantity'] * $attributes['price'];
            },
        ];
    }
}

