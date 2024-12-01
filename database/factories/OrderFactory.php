<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use App\Models\Group;
use App\Models\OrderStatus;
use App\Models\PaymentMethod;
use App\Models\DeliveryMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition()
    {
        return [
            'order_status_id' => OrderStatus::factory(),
            'payment_method_id' => PaymentMethod::factory(),
            'delivery_method_id' => DeliveryMethod::factory(),
            'group_id' => Group::factory(),
            'responsible_user_id' => User::factory(),
            'delivery_price' => $this->faker->randomFloat(2, 5, 50),
            'delivery_fullname' => $this->faker->name(),
            'delivery_address' => $this->faker->address(),
            'delivery_postcode' => $this->faker->postcode(),
            'delivery_city' => $this->faker->city(),
            'delivery_country_code' => $this->faker->countryCode(),
            'email' => $this->faker->email(),
            'phone' => $this->faker->phoneNumber(),
            'ip' => $this->faker->ipv4(),
            'comment' => $this->faker->sentence(),
            'website_referrer' => $this->faker->url(),
        ];
    }
}
