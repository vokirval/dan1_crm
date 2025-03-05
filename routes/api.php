<?php

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\OrderLockController;

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

        // Ищем заказ по external_id (InPost ID)
        $order = Order::where('inpost_id', $webhookData['external_id'])->first();

        if (!$order) {
            Log::warning('Order not found for InPost ID.', ['external_id' => $webhookData['external_id']]);
            return response()->json([
                'message' => 'Order not found.',
            ], 404);
        }

        // Обновляем данные заказа
        $updateData = [];
        if (!empty($webhookData['tracking_number']) && empty($order->tracking_number)) {
            $updateData['tracking_number'] = $webhookData['tracking_number'];
        }
        if (!empty($webhookData['return_tracking_number']) && empty($order->return_tracking_number)) {
            $updateData['return_tracking_number'] = $webhookData['return_tracking_number'];
        }
        if (!empty($webhookData['inpost_status'])) {
            $updateData['inpost_status'] = $webhookData['inpost_status'];
        }

        if (!empty($updateData)) {
            $order->update($updateData);
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
            'tracking_number' => 'nullable|string', // Трекинг номер, может быть null
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

        // Обновляем трекинг номер, если он присутствует
        if (!empty($webhookData['tracking_number'])) {
            $order->update([
                'tracking_number' => $webhookData['tracking_number'],
            ]);
        }

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
