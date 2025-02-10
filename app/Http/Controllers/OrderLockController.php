<?php

namespace App\Http\Controllers;

use App\Models\OrderLock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class OrderLockController extends Controller
{
    // Блокировка заказа
    public function lock(Request $request, $orderId)
    {
        $userId = auth()->id();

        // Проверяем, не заблокирован ли уже заказ
        $existingLock = OrderLock::where('order_id', $orderId)->first();

        if ($existingLock) {
            // Если заказ уже заблокирован этим пользователем, обновляем время активности и разрешаем доступ
            if ($existingLock->user_id === $userId) {
                $existingLock->update(['last_heartbeat' => Carbon::now()]);
                return response()->json(['message' => 'Вы продолжаете редактирование заказа']);
            }

            // Если заказ заблокирован другим пользователем — отказываем в доступе
            return response()->json(['error' => 'Замовлення заблоковано! Хтось його вже редагує.'], 403);
        }

        // Создаем запись о блокировке
        OrderLock::create([
            'order_id' => $orderId,
            'user_id' => $userId,
            'last_heartbeat' => Carbon::now(),
        ]);

        // Отправляем сообщение в Ably напрямую
        $this->sendToAbly('order.locked', [
            'orderId' => $orderId,
            'userId' => $userId,
        ]);

        return response()->json(['message' => 'Заказ заблокирован']);
    }

    // Разблокировка заказа
    public function unlock(Request $request, $orderId)
    {
        $userId = auth()->id();

        OrderLock::where('order_id', $orderId)
            ->where('user_id', $userId)
            ->delete();

        // Отправляем сообщение в Ably напрямую
        $this->sendToAbly('order.unlocked', ['orderId' => $orderId]);

        return response()->json(['message' => 'Заказ разблокирован']);
    }

    public function getLockedOrders()
{
    $lockedOrders = OrderLock::pluck('order_id')->toArray();
    return response()->json(['lockedOrders' => $lockedOrders]);
}

    // Обновление пинга (heartbeat)
    public function heartbeat(Request $request, $orderId)
    {
        $userId = auth()->id();

        OrderLock::where('order_id', $orderId)
            ->where('user_id', $userId)
            ->update(['last_heartbeat' => Carbon::now()]);

        return response()->json(['message' => 'Heartbeat обновлен']);
    }

    /**
     * Отправка событий в Ably напрямую через API
     */
    private function sendToAbly($eventName, $data)
    {
        $ablyKey = env('ABLY_KEY'); // Ключ берём из .env
        $channel = "orders";

        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . base64_encode($ablyKey),
            'Content-Type' => 'application/json',
        ])->post("https://rest.ably.io/channels/{$channel}/publish", [
            'name' => $eventName,
            'data' => $data,
        ]);

        if ($response->failed()) {
            \Log::error("Ошибка отправки события в Ably: {$eventName}", [
                'response' => $response->json(),
                'status' => $response->status()
            ]);
        }
    }
}
