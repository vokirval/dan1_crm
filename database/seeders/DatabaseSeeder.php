<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\ProductVariationAttribute;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatus;
use App\Models\PaymentMethod;
use App\Models\DeliveryMethod;
use App\Models\Group;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Создание статусов заказов
        $statuses = OrderStatus::factory(5)->create();

        // 2. Методы оплаты и доставки
        $paymentMethods = PaymentMethod::factory(3)->create();
        $deliveryMethods = DeliveryMethod::factory(3)->create();

        // 3. Группы и пользователи
        $groups = Group::factory(5)->create();
        $groups->each(function ($group) {
            $group->users()->attach(User::factory(1)->create()->pluck('id')->toArray());
        });

        // 4. Категории и продукты
        $categories = Category::factory()
            ->count(5)
            ->create();

        $categories->each(function ($category) {
            Product::factory()
                ->count(10)
                ->for($category, 'category')
                ->create()
                ->each(function ($product) {
                    if ($product->type === 'variable') {
                        ProductVariation::factory()
                            ->count(5)
                            ->for($product, 'product')
                            ->create()
                            ->each(function ($variation) {
                                ProductVariationAttribute::factory()
                                    ->count(2)
                                    ->for($variation, 'variation')
                                    ->create();
                            });
                    }
                });
        });

        // Продукты без категорий
        $simpleProducts = Product::factory()
            ->count(10)
            ->create();

        // 5. Создание заказов
        Order::factory(10)->create([
            'order_status_id' => $statuses->random()->id,
            'payment_method_id' => $paymentMethods->random()->id,
            'delivery_method_id' => $deliveryMethods->random()->id,
            'group_id' => $groups->random()->id,
        ])->each(function ($order) use ($simpleProducts) {
            // Добавление товаров в заказ
            for ($i = 0; $i < 3; $i++) {
                if (rand(0, 1)) {
                    // Простые товары
                    $product = $simpleProducts->random();
                    OrderItem::factory()->create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'product_variation_id' => null,
                    ]);
                } else {
                    // Вариативные товары
                    $variation = ProductVariation::inRandomOrder()->first();
                    OrderItem::factory()->create([
                        'order_id' => $order->id,
                        'product_id' => null,
                        'product_variation_id' => $variation->id,
                    ]);
                }
            }
        });
    }
}
