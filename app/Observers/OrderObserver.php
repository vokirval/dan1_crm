<?php

namespace App\Observers;

use App\Models\Order;
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
        $triggerStatuses = [7];

        if (!in_array($order->order_status_id, $triggerStatuses)) {
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
                'external_id' => $order->id, // ID заказа из вашей системы
                'name' => $order->delivery_fullname ?? 'Unknown',
                'phone' => $order->phone ?? 'Unknown',
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
                \Log::info('Order sent to Panel Expansion', [
                    'order_id' => $order->id,
                    'response' => $response->json(),
                ]);
            } else {
                \Log::error('Failed to send order to Panel Expansion', [
                    'order_id' => $order->id,
                    'response' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Error sending order to Panel Expansion', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

}