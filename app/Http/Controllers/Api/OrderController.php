<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        // Валидация данных
        $validated = $request->validate([
            'order_status_id' => 'nullable|exists:order_statuses,id',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
            'delivery_method_id' => 'nullable|exists:delivery_methods,id',
            'group_id' => 'nullable|exists:groups,id',
            'responsible_user_id' => 'nullable|exists:users,id',
            'delivery_price' => 'nullable|numeric|min:0',
            'delivery_fullname' => 'nullable|string|max:255',
            'delivery_address' => 'nullable|string|max:255',
            'delivery_second_address' => 'nullable|string|max:255',
            'delivery_postcode' => 'nullable|string|max:20',
            'delivery_city' => 'nullable|string|max:100',
            'delivery_state' => 'nullable|string|max:100',
            'delivery_country_code' => 'nullable|string|max:10',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'ip' => 'nullable|ip', // Делаем поле IP необязательным на этапе валидации
            'comment' => 'nullable|string',
            'website_referrer' => 'nullable|url',
            'utm_source' => 'nullable|string|max:255',
            'utm_medium' => 'nullable|string|max:255',
            'utm_term' => 'nullable|string|max:255',
            'utm_content' => 'nullable|string|max:255',
            'utm_campaign' => 'nullable|string|max:255',
            'sub_ids' => 'array',
            'sub_ids.*' => 'nullable|string|max:255',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.product_variation_id' => 'nullable|exists:product_variations,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        // Устанавливаем IP-адрес пользователя, если он не предоставлен
        $validated['ip'] = $validated['ip'] ?? $request->ip();

        $orderData = collect($validated)->except('items')->toArray();
        $order = Order::create($orderData);

        // Создаем элементы заказа
        foreach ($validated['items'] as $item) {
            $item['product_variation_id'] = $item['product_variation_id'] ?? null;
            $order->items()->create([
                'product_id' => $item['product_variation_id'] ? null : $item['product_id'],
                'product_variation_id' => $item['product_variation_id'] ?? null,
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'subtotal' => $item['quantity'] * $item['price'],
            ]);
        }

        // Возвращаем успешный ответ
        return response()->json(['order_id' => $order->id], 201);
    }

    public function show()
    {
        return response()->json(['message' => 'Order'], 201);
    }

}