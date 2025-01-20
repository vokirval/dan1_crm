<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use Inertia\Inertia;
use App\Models\Group;
use App\Models\Order;
use App\Models\Product;
use App\Models\OrderStatus;
use Illuminate\Http\Request;
use App\Models\EmailTemplate;
use App\Models\PaymentMethod;
use App\Models\DeliveryMethod;


class OrdersController extends Controller
{
    /**
     * Показать список заказов с пагинацией.
     */
    public function index(Request $request)
    {
        $perPage = min($request->input('per_page', 10), 100);
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $statusId = $request->input('status_id');
        $filters = $request->only(['id', 'delivery_fullname', 'phone', 'ip', 'email']);
        $user = auth()->user();

        // Разрешенные поля для сортировки
        $allowedSortFields = ['id', 'created_at', 'updated_at', 'delivery_fullname', 'phone', 'email', 'delivery_city'];

        // Проверка сортировки
        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'created_at';
        }

        // Проверяем, является ли пользователь супер-админом
        if ($user->hasRole('Super Admin')) {
            $ordersQuery = Order::query();
        } else {
            // Получаем ID групп, к которым принадлежит пользователь
            $userGroupIds = $user->groups->pluck('id');
            // Ограничиваем выборку заказов только группами пользователя
            $ordersQuery = Order::whereIn('group_id', $userGroupIds);
        }

        // Фильтрация по статусу, если статус выбран
        if ($statusId) {
            $ordersQuery->where('order_status_id', $statusId);
        }

        // Фильтрация по каждому полю
        foreach ($filters as $key => $value) {
            if ($value) {
                $ordersQuery->where($key, 'like', "%{$value}%");
            }
        }

        // Применяем сортировку и пагинацию
        $orders = $ordersQuery
            ->with([
            'status', 
            'paymentMethod', 
            'deliveryMethod', 
            'group', 
            'responsibleUser', 
            'items.product:name,id',
                'items.productVariation:id,product_id',
                'items.productVariation.product:name,id',
                'items.productVariation.attributes:product_variation_id,attribute_name,attribute_value'])
            ->orderBy($sortBy, $sortDirection)
            ->paginate($perPage)
            ->appends($request->only(['per_page', 'sort_by', 'sort_direction']));


        // Проверяем, является ли пользователь супер-админом
        if ($user->hasRole('Super Admin')) {
            $statusesWithCounts = Order::query()
            ->selectRaw('order_status_id, COUNT(*) as orders_count')
            ->groupBy('order_status_id')
            ->pluck('orders_count', 'order_status_id');
        } else {
            // Получаем ID групп, к которым принадлежит пользователь
            $userGroupIds = $user->groups->pluck('id');
            // Ограничиваем выборку заказов только группами пользователя
            $statusesWithCounts = Order::whereIn('group_id', $userGroupIds)
            ->selectRaw('order_status_id, COUNT(*) as orders_count')
            ->groupBy('order_status_id')
            ->pluck('orders_count', 'order_status_id');
        }


        // Получение всех статусов с добавлением количества заказов
        $statuses = OrderStatus::all()->map(function ($status) use ($statusesWithCounts) {
            $status->orders_count = $statusesWithCounts->get($status->id, 0);
            return $status;
        });

        return Inertia::render('Orders/Index', [
            'data' => $orders,
            'statuses' => $statuses,
            'currentStatusId' => $statusId, // Передаем текущий статус для синхронизации
            'filters' => $filters, // Передаем текущие фильтры
        ]);
    }

    /**
     * Показать форму для создания нового заказа.
     */
    public function create()
    {
        $statuses = OrderStatus::all();
        $paymentMethods = PaymentMethod::all();
        $deliveryMethods = DeliveryMethod::all();
        $groups = Group::all();
        $users = User::all();
        $products = Product::with('variations.attributes')->get();

        return Inertia::render('Orders/Create', [
            'statuses' => $statuses,
            'paymentMethods' => $paymentMethods,
            'deliveryMethods' => $deliveryMethods,
            'groups' => $groups,
            'users' => $users,
            'products' => $products,
        ]);
    }

    /**
     * Сохранить новый заказ.
     */
    public function store(Request $request)
    {
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
            'email' => 'nullable|string|max:255',
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
            'delivery_date' => 'nullable|date_format:Y-m-d H:i',
            'payment_date' => 'nullable|date_format:Y-m-d H:i',
            'tracking_number' => 'nullable|string|max:255', // Новое поле
            'is_paid' => 'nullable|boolean', // Новое поле
            'paid_amount' => 'nullable|numeric|min:0', // Новое поле
        ]);

        // Удаляем пробелы в номере телефона
        if (!empty($validated['phone'])) {
            $validated['phone'] = str_replace([' ', '+', '-', '(', ')'], '', $validated['phone']);
        }
        

        // Устанавливаем IP-адрес пользователя, если он не предоставлен
        $validated['ip'] = $validated['ip'] ?? $request->ip();

        // Создаем заказ
        $orderData = collect($validated)->except('items')->toArray();
        $order = Order::create($orderData);

        // Создаем элементы заказа
        foreach ($validated['items'] as $item) {
            $order->items()->create([
                'product_id' => $item['product_variation_id'] ? null : $item['product_id'],
                'product_variation_id' => $item['product_variation_id'] ?? null,
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'subtotal' => $item['quantity'] * $item['price'],
            ]);
        }

        return redirect()->route('orders.index')->with('success', 'Order created successfully.');
    }



    /**
     * Показать заказ.
     */
    public function show($id)
    {
        $user = auth()->user();
        $userGroupIds = $user->groups->pluck('id');

        if ($user->hasRole('Super Admin')) {
            $order = Order::with([
                'items.product:name,id',
                'items.productVariation:id,product_id',
                'items.productVariation.product:name,id',
                'items.productVariation.attributes',
                'emailHistory' // Подгружаем историю писем
            ])->findOrFail($id);
        } else {
            $userGroupIds = $user->groups->pluck('id');
            if ($userGroupIds->isEmpty()) {
                abort(404, 'Order not found for the user group.');
            }

            $order = Order::whereIn('group_id', $userGroupIds)
                ->with([
                    'items.product:name,id',
                    'items.productVariation:id,product_id',
                    'items.productVariation.product:name,id',
                    'items.productVariation.attributes',
                    'emailHistory' // Подгружаем историю писем
                ])
                ->findOrFail($id);
        }

        // Проверка на совпадения по номеру телефона, email и IP
        $duplicateOrders = Order::where(function ($query) use ($order) {
            $query->where('phone', $order->phone)
                ->orWhere('email', $order->email)
                ->orWhere('ip', $order->ip);
        })
        ->where('id', '<>', $order->id) // Исключаем текущий заказ
        ->with([
            'status', 
            'paymentMethod', 
            'deliveryMethod', 
            'group', 
            'responsibleUser', 
            'items.product:name,id',
                'items.productVariation:id,product_id',
                'items.productVariation.product:name,id',
                'items.productVariation.attributes:product_variation_id,attribute_name,attribute_value'])
        ->get();

        $statuses = OrderStatus::all();
        $payment_methods = PaymentMethod::all();
        $delivery_methods = DeliveryMethod::all();
        $groups = Group::all();
        $users = User::all();
        $products = Product::with('variations.attributes')->get();
        $emailTemplates = EmailTemplate::all(['id', 'name', 'subject', 'body']);

        return Inertia::render('Orders/Show', [
            'order' => $order,
            'statuses' => $statuses,
            'payment_methods' => $payment_methods,
            'delivery_methods' => $delivery_methods,
            'groups' => $groups,
            'users' => $users,
            'products' => $products,
            'duplicateOrders' => $duplicateOrders, // Добавляем массив дублей заказов
            'emailTemplates' => $emailTemplates, // Передаем шаблоны
        ]);
    }


    /**
     * Обновить заказ.
     */
    public function update(Request $request, $id)
    {
        $order = Order::findOrFail($id);

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
            'email' => 'nullable|string|max:255',
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
            'delivery_date' => 'nullable|date',
            'payment_date' => 'nullable|date',
            'tracking_number' => 'nullable|string|max:255', // Новое поле
            'is_paid' => 'nullable|boolean', // Новое поле
            'paid_amount' => 'nullable|numeric|min:0', // Новое поле
        ]);

        

        // Ограничения на смену статуса
        $newStatusId = $validated['order_status_id'] ?? null;
        $currentStatusId = $order->order_status_id;
        $newStatusIdForNew = 1; // ID статуса "new"
        $allowedStatusesForNew = [2, 11, 13]; // IDs статусов: "Апрув", "Кенсел", "Коррекшен Овн"

        // Проверяем, можно ли сменить статус
        if ($currentStatusId === $newStatusIdForNew && $newStatusId !== null && !in_array($newStatusId, $allowedStatusesForNew)) {
            return back()->withErrors([
                'order_status_id' => 'Проблема статусу! Оберіть інший статус!.',
            ]);
        }

        // Обновляем заказ
        $order->update($validated);

        return back()->with('success', 'Order updated successfully.');
    }

    /**
     * Удалить заказ.
     */
    public function destroy($id)
    {
        $order = Order::findOrFail($id);
        $order->delete();

        return back()->with('success', 'Order deleted successfully.');
    }

    /**
     * Добавить элементы заказа.
     */
    public function addOrderItems(Request $request, $orderId)
    {
        $order = Order::findOrFail($orderId);

        $validatedItems = $request->validate([
            'product_id' => 'nullable|exists:products,id',
            'product_variation_id' => 'nullable|exists:product_variations,id',
            'quantity' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
        ]);

        $order->items()->create([
            'product_id' => $validatedItems['product_variation_id'] ? null : $validatedItems['product_id'],
            'product_variation_id' => $validatedItems['product_variation_id'] ?? null,
            'quantity' => $validatedItems['quantity'],
            'price' => $validatedItems['price'],
            'subtotal' => $validatedItems['quantity'] * $validatedItems['price'],
        ]);

        $order = Order::with([
            'items.product:name,id', // Подгружаем только name и id для основного продукта
            'items.productVariation:id,product_id', // Подгружаем только id и связь с основным продуктом
            'items.productVariation.product:name,id', // Подгружаем только name для основного продукта вариации
            'items.productVariation.attributes'
        ])->findOrFail($orderId);

        return response()->json([
            'order' => $order,
            'flash' => ['success' => 'Товар успешно добавлен.'],
        ]);
    }

    public function updateOrderItem(Request $request, $orderId, $itemId)
    {
        $order = Order::findOrFail($orderId);
        $item = $order->items()->findOrFail($itemId);

        $validated = $request->validate([
            'quantity' => 'sometimes|integer|min:1',
            'price' => 'sometimes|numeric|min:0',
        ]);

        $item->update($validated);

        // Пересчитываем subtotal
        $item->update([
            'subtotal' => $item->quantity * $item->price,
        ]);

        $order = Order::with([
            'items.product:name,id', // Подгружаем только name и id для основного продукта
            'items.productVariation:id,product_id', // Подгружаем только id и связь с основным продуктом
            'items.productVariation.product:name,id', // Подгружаем только name для основного продукта вариации
            'items.productVariation.attributes'
        ])->findOrFail($orderId);

        return response()->json([
            'order' => $order,
            'flash' => ['success' => 'Товар успешно обновлен.'],
        ]);
    }



    /**
     * Удалить элемент заказа.
     */
    public function removeOrderItem($orderId, $itemId)
    {
        $order = Order::findOrFail($orderId);
        $orderItem = $order->items()->findOrFail($itemId);

        $orderItem->delete();

        return back()->with('success', 'Товар успешно удален из заказа.');
    }


    /**
     * Массовое удаление заказов.
     */
    public function massDelete(Request $request)
    {
        $validated = $request->validate([
            'order_ids' => 'required|array',
            'order_ids.*' => 'exists:orders,id',
        ]);

        Order::whereIn('id', $validated['order_ids'])->delete();

        return back()->with('success', 'Замовлення успішно видалені!');

    }

    /**
     * Массовая смена статусов заказов.
     */
    public function massUpdateStatus(Request $request)
    {
        $validated = $request->validate([
            'order_ids' => 'required|array',
            'order_ids.*' => 'exists:orders,id',
            'status_id' => 'required|exists:order_statuses,id',
        ]);
    
        $newStatusId = $validated['status_id'];
        $newStatusIdForNew = 1; // ID статуса "new"
         $allowedStatusesForNew = [2, 11, 13]; // IDs статусов: "Апрув", "Кенсел", "Коррекшен Овн"
    
        $orders = Order::whereIn('id', $validated['order_ids'])->get();
    
        foreach ($orders as $order) {
            // Если текущий статус "new", проверяем разрешенные статусы
            if ($order->order_status_id === $newStatusIdForNew && !in_array($newStatusId, $allowedStatusesForNew)) {
                \Log::warning('Невозможно изменить статус заказа с "new" на недопустимый статус.', [
                    'order_id' => $order->id,
                    'attempted_status' => $newStatusId,
                ]);
                continue; // Пропускаем заказ
            }
    
            // Обновляем статус заказа
            $order->update(['order_status_id' => $newStatusId]);
        }

        return back()->with('success', 'Статус успішно оновлено!');
    }

    /**
     * Массовое изменение комментариев в заказах.
     */
    public function massUpdateComment(Request $request)
    {
        $validated = $request->validate([
            'order_ids' => 'required|array',
            'order_ids.*' => 'exists:orders,id',
            'comment' => 'nullable|string',
        ]);

        Order::whereIn('id', $validated['order_ids'])->update(['comment' => $validated['comment']]);

        return back()->with('success', 'Коментар успішно оновлено!');
    }


}
