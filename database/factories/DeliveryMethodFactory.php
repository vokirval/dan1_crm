<?php

namespace Database\Factories;

use App\Models\DeliveryMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeliveryMethodFactory extends Factory
{
    protected $model = DeliveryMethod::class;

    public function definition()
    {
        return [
            'name' => $this->faker->randomElement(['INPOST', 'DHL', 'POCZTA_POLSKA']),
        ];
    }
}

