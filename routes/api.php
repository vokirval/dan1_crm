<?php

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\OrderController;
use App\Models\OrderFulfillment;
use App\Http\Controllers\OrderLockController;
use Carbon\Carbon;


Route::middleware('auth:sanctum')->group(function () {
    Route::post('/orders', [OrderController::class, 'store']);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/inpost-webhook', function (Request $request) {
    try {
        // Получаем данные из вебхука
        $webhookData = $request->validate([
            'inpost_id' => 'required|string',
            'tracking_number' => 'nullable|string',
            'return_tracking_number' => 'nullable|string',
            'inpost_status' => 'nullable|string',
        ]);

        // Ищем заказ по inpost_id (InPost ID)
        $order = Order::where('inpost_id', $webhookData['inpost_id'])->first();

        $alreadySent = OrderFulfillment::where('order_id', $order->id)->where('sent', true)->exists();

        if (!$order) {
            Log::warning('Order not found for InPost ID.', ['inpost_id' => $webhookData['inpost_id']]);
            return response()->json([
                'message' => 'Order not found.',
            ], 404);
        }

        // Обновляем данные заказа
        $updateData = [];
        $logMessages = [];

        if (!empty($webhookData['tracking_number']) && empty($order->tracking_number)) {
            $updateData['tracking_number'] = $webhookData['tracking_number'];
            $logMessages[] = 'Додано tracking_number: ' . $webhookData['tracking_number'];
        }
        if (!empty($webhookData['return_tracking_number']) && empty($order->return_tracking_number)) {
            $updateData['return_tracking_number'] = $webhookData['return_tracking_number'];
            $logMessages[] = 'Додано return_tracking_number: ' . $webhookData['return_tracking_number'];
        }
        if (!empty($webhookData['inpost_status'])) {
            $updateData['inpost_status'] = $webhookData['inpost_status'];
            $logMessages[] = 'Змінено статус InPost: ' . $webhookData['inpost_status'];

            // Если статус "delivered", записываем дату доставки
            if (strtolower($webhookData['inpost_status']) === 'delivered') {
                $updateData['delivery_date'] = Carbon::now()->format('Y-m-d H:i');
                $logMessages[] = 'Замовлення доставлено, Дата отримання: ' . $updateData['delivery_date'];
            }

            // Если статус "delivered", записываем дату доставки
            if (strtolower($webhookData['inpost_status']) === 'collected_from_sender') {
                $updateData['sent_at'] = Carbon::now()->format('Y-m-d H:i');
                $logMessages[] = 'Замовлення відправлено, Дата відправки: ' . $updateData['sent_at'];
            }
        }

        if (!empty($updateData)) {
            $order->update($updateData);

            if ($alreadySent) {
                OrderFulfillment::create([
                    'order_id' => $order->id,
                    'sent' => true,
                    'comment' => '[Webhook Inpost] Замовлення оновлено: ' . implode('; ', $logMessages),
                ]);
            } else {
                OrderFulfillment::create([
                    'order_id' => $order->id,
                    'sent' => false,
                    'comment' => '[Webhook Inpost] Замовлення оновлено: ' . implode('; ', $logMessages),
                ]);
            }
            


            Log::info('Order updated from InPost webhook.', ['order_id' => $order->id, 'data' => $updateData]);
        }

        return response()->json([
            'message' => 'Order updated successfully.',
            'order' => $order,
        ]);
    } catch (\Exception $e) {
        Log::error('Error processing InPost webhook', ['error' => $e->getMessage()]);

        return response()->json([
            'message' => 'An error occurred.',
            'error' => $e->getMessage(),
        ], 500);
    }
});

Route::get('/orders/{order}/check-tracking', function (Order $order) {
    return response()->json([
        'tracking_number' => $order->tracking_number,
    ]);
});

Route::post('/change_status/{status_id}', function (Request $request, $status_id) {
    try {
	
        // Получаем данные из вебхукаs
        $webhookData = $request->validate([
            'external_id' => 'required|string', // Айдишник заказа
        ]);
		
        // Ищем заказ по external_id
        $order = Order::where('id', $webhookData['external_id'])->first();
		
        if (!$order) {
			Log::warning('Order not found for external_id.', ['external_id' => $webhookData['external_id']]);
			return response()->json([
				'message' => 'Order not found.',
			], 404);
		}

        // Обновляем статус заказа
        $order->update([
            'order_status_id' => $status_id,
        ]);

        OrderFulfillment::create([
            'order_id' => $order->id,
            'sent' => true,
            'comment' => '[Webhook Fulfillment] Зміна статуса на: ' . $status_id,
        ]);


        return response()->json([
            'message' => 'Order status updated successfully.',
            'order' => $order,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'An error occurred.',
            'error' => $e->getMessage(),
        ], 500);
    }
});
