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

            // Формируем данные для API
            $requestData = [
                'user_id' => 12, // Ваш идентификатор пользователя в API
                'external_id' => (string) $order->id, // ID заказа из вашей системы
                'name' => $order->delivery_fullname ?? 'Unknown',
                'phone' => $order->phone ?? 'Unknown',
                'email' => $order->email ?? 'Unknown',
                'delivery_method' => 'INPOST', // Пример значения
                'amount' => $amount, // Общая сумма заказа
                'payment_method' => 'CASH_ON_DELIVERY', // Замените на актуальное
                'shipment_payer' => 'RECEIVER',
                'shipment_type' => 'ADDRESS',
                'country' => 'Poland',
                'city' => $order->delivery_city ?? 'Unknown',
                'address' => $order->delivery_address ?? 'Unknown',
                'second_address' => $order->delivery_second_address ?? '',
                'products' => $products,
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

                throw new \Exception('Failed to send order: ' . $response->body());
            }
        } catch (\Exception $e) {

            // Меняем статус заказа на 12
           $order->update(['order_status_id' => 12]);


            \Log::error('Error sending order to Panel Expansion', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

}