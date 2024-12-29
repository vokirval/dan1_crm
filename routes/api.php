<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\OrderController;
use App\Models\Order;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/orders', [OrderController::class, 'store']);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/change_status/{status_id}', function (Request $request, $status_id) {
    try {
        // Получаем данные из вебхука
        $webhookData = $request->validate([
            'external_id' => 'required|string', // Айдишник заказа
        ]);

        // Ищем заказ по external_id
        $order = Order::where('external_id', $webhookData['external_id'])->first();

        if (!$order) {
            return response()->json([
                'message' => 'Order not found.',
            ], 404);
        }

        // Обновляем статус заказа
        $order->update([
            'order_status_id' => $status_id,
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
