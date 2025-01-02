<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\OrderFulfillment;
use Illuminate\Support\Facades\Http;

class OrderObserver
{
    public function created(Order $order)
    {
        //$this->checkAndSendWebhook($order);
    }

    public function updated(Order $order)
    {
        defer(function() use ($order) {
            sleep(10);
            $this->panelExpansion($order);
        });
        
       
    }

    protected function panelExpansion(Order $order)
    {
        // Убедимся, что статус заказа подходит
        $triggerStatuses = [2];

        if (!in_array($order->order_status_id, $triggerStatuses)) {
            return;
        }

        // Проверяем, был ли заказ уже отправлен
        $alreadySent = OrderFulfillment::where('order_id', $order->id)->where('sent', true)->exists();

        if ($alreadySent) {
            \Log::info('Order already sent to Panel Expansion', ['order_id' => $order->id]);
            return;
        }

        // Проверка флага оплаты, если метод оплаты не равен 1 (COD)
        if ($order->payment_method_id != 1 && !$order->is_paid) {
            // Если заказ не оплачен, переводим в статус 12 и логируем
            $order->update(['order_status_id' => 12]);
            OrderFulfillment::create([
                'order_id' => $order->id,
                'sent' => false,
                'comment' => 'Order not sent: payment not completed for non-COD method.',
            ]);
            \Log::warning('Order not sent to Panel Expansion: payment not completed for non-COD method.', ['order_id' => $order->id]);
            return;
        }

        // Жадная загрузка товаров с минимальными полями
        $order->load(['items.product:id,id,sku', 'items.productVariation:id,id,sku']);

        try {
            // Формируем массив товаров для API и рассчитываем общую сумму
            $products = $order->items->map(function ($item) {
                $sku = $item->productVariation->sku ?? $item->product->sku ?? 'unknown'; // SKU из вариации или продукта
                return [
                    'sku_code' => $sku,
                    'quantity' => $item->quantity ?? 1,
                    'price' => $item->price ?? 0, // Цена за единицу, если есть
                ];
            });

            // Расчет общей суммы
            $amount = $products->sum(function ($product) {
                return $product['quantity'] * $product['price'];
            });

            // Преобразование в массив для API
            $productsArray = $products->toArray();
            
            // Определяем метод оплаты
        $paymentMethod = $order->payment_method_id == 1 ? 'CASH_ON_DELIVERY' : 'PREPAY';

        // Проверяем и разбиваем имя и фамилию
        $nameParts = explode(' ', trim($order->delivery_fullname));
        if (count($nameParts) < 2) {
            // Если фамилия отсутствует, переводим заказ в статус 12
            $order->update(['order_status_id' => 12]);
            OrderFulfillment::create([
                'order_id' => $order->id,
                'sent' => false,
                'comment' => 'Missing surname in delivery_fullname.',
            ]);
            \Log::warning('Order not sent: missing surname', ['order_id' => $order->id]);
            return;
        }

        $name = $nameParts[0]; // Имя
        $surname = implode(' ', array_slice($nameParts, 1)); // Остальные части как фамилия

        // Формируем данные для API
        $requestData = [
            'user_id' => 12, // Ваш идентификатор пользователя в API
            'external_id' => (string) $order->id, // ID заказа из вашей системы
            'name' => $name,
            'surname' => $surname,
            'phone' => $order->phone ?? 'Unknown',
            'email' => $order->email ?? 'Unknown',
            'delivery_method' => 'INPOST', // Пример значения
            'amount' => $amount, // Общая сумма заказа
            'payment_method' => $paymentMethod,
            'shipment_payer' => 'RECEIVER',
            'shipment_type' => 'ADDRESS',
            'country' => 'Poland',
            'city' => $order->delivery_city ?? 'Unknown',
            'address' => $order->delivery_address ?? 'Unknown',
            'second_address' => $order->delivery_second_address ?? '',
            'products' => $productsArray,
            'comment' => $order->comment ?? '',
            'postal_office' => $order->delivery_postcode ?? 'Unknown',
            'currency' => 'PLN'
        ];

            $apiToken = env('PANEL_EXPANSION_API_TOKEN');

            // Отправка запроса в API
            $response = Http::withToken($apiToken) // Ваш API ключ
                ->post('https://panel.expansion-fulfillment.com/api/orders/create', $requestData);

            // Проверка результата
            if ($response->successful()) {
                // Сохраняем пометку об отправке
                OrderFulfillment::create([
                    'order_id' => $order->id,
                    'sent' => true,
                    'comment' => 'Order successfully sent to fulfillment.',
                ]);
    
                \Log::info('Order sent to Panel Expansion', [
                    'order_id' => $order->id,
                    'response' => $response->json(),
                ]);
            } else {
                // Меняем статус заказа на 12
              $order->update(['order_status_id' => 12]);
                OrderFulfillment::create([
                    'order_id' => $order->id,
                    'sent' => false,
                    'comment' => 'Failed to send order: ' . $response->body(),
                ]);

                throw new \Exception('Failed to send order: ' . $response->body());
            }
        } catch (\Exception $e) {

            // Меняем статус заказа на 12
           $order->update(['order_status_id' => 12]);
           OrderFulfillment::create([
                'order_id' => $order->id,
                'sent' => false,
                'comment' => 'Error sending order: ' . $e->getMessage(),
            ]);


            \Log::error('Error sending order to Panel Expansion', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

}