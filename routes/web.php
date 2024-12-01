<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\OrderStatusController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\DeliveryMethodController;
use App\Http\Controllers\ProductsCategoryController;

//php artisan migrate
//php artisan db:seed --class=DatabaseSeeder
//php artisan db:seed --class=PermissionsSeeder
//php artisan db:seed --class=SuperAdminSeeder

Route::get('/', [HomeController::class, 'index']);

Route::get('/login', [AuthController::class, 'login'])->name('login');
Route::post('/login', [AuthController::class, 'storeLogin'])->name('store-login');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/signup', [AuthController::class, 'signup'])->name('signup');
Route::post('/signup', [AuthController::class, 'storeSignup'])->name('store-signup');

use App\Models\User;


Route::get('/generate-token', function () {
    // Найти пользователя с именем "admin"
    $user = User::where('name', 'admin')->first();

    if (!$user) {
        return "User with name 'admin' not found.";
    }

    // Создать токен для найденного пользователя
    $token = $user->createToken('Orders API Token')->plainTextToken;

    // Вернуть токен
    return "Generated token: $token";
});



Route::middleware(['auth'])->group(function() {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    
    Route::prefix('/products')->group(function(){

        //products
        Route::get('/', [ProductsController::class, 'index'])->middleware('permission:Перегляд продуктів');
        Route::post('/', [ProductsController::class, 'store'])->middleware('permission:Створення продуктів');

        Route::get('/{id}', [ProductsController::class, 'single'])->middleware('permission:Перегляд продуктів');
        Route::put('/{id}', [ProductsController::class, 'update'])->middleware('permission:Редагування продуктів');
        Route::delete('/{id}', [ProductsController::class, 'destroy'])->middleware('permission:Видалення продуктів');
        
        Route::post('/{id}/variations', [ProductsController::class, 'storeVariation'])->middleware('permission:Створення продуктів');
        Route::delete('/{id}/variations/{variationId}', [ProductsController::class, 'destroyVariation'])->middleware('permission:Видалення продуктів');

    });

    Route::prefix('/categories')->group(function(){
        Route::get('/', [ProductsCategoryController::class, 'index'])->middleware('permission:Перегляд категорій');
        Route::post('/', [ProductsCategoryController::class, 'store'])->middleware('permission:Створення категорій');
        Route::put('/{id}', [ProductsCategoryController::class, 'update'])->middleware('permission:Редагування категорій');
        Route::delete('/{id}', [ProductsCategoryController::class, 'destroy'])->middleware('permission:Видалення категорій');
    });

    Route::prefix('/orders')->group(function () {
        Route::get('/', [OrdersController::class, 'index'])->name('orders.index')->middleware('permission:Перегляд замовлень'); 
        Route::get('/create', [OrdersController::class, 'create'])->name('orders.create')->middleware('permission:Створення замовлень');
        Route::post('/', [OrdersController::class, 'store'])->name('orders.store')->middleware('permission:Створення замовлень');
        Route::get('/{id}', [OrdersController::class, 'show'])->name('orders.show')->middleware('permission:Перегляд замовлень');
        
        Route::put('/{id}', [OrdersController::class, 'update'])->name('orders.update')->middleware('permission:Редагування замовлень');
        Route::delete('/{id}', [OrdersController::class, 'destroy'])->name('orders.destroy')->middleware('permission:Видалення замовлень');
        
        Route::post('/{id}/items', [OrdersController::class, 'addOrderItems'])->name('orders.add_items')->middleware('permission:Створення замовлень');
        Route::put('/{orderId}/items/{itemId}', [OrdersController::class, 'updateOrderItem'])->name('orders.items.update')->middleware('permission:Редагування замовлень');
        Route::delete('/{orderId}/items/{itemId}', [OrdersController::class, 'removeOrderItem'])->name('orders.items.destroy')->middleware('permission:Видалення замовлень');


    });

    Route::prefix('/order-statuses')->group(function () {
        Route::get('/', [OrderStatusController::class, 'index'])->name('order-statuses.index')->middleware('permission:Перегляд статусів замовлень');
        Route::post('/', [OrderStatusController::class, 'store'])->name('order-statuses.store')->middleware('permission:Створення статусів замовлень');
        Route::put('/{id}', [OrderStatusController::class, 'update'])->name('order-statuses.update')->middleware('permission:Редагування статусів замовлень');
        Route::delete('/{id}', [OrderStatusController::class, 'destroy'])->name('order-statuses.destroy')->middleware('permission:Видалення статусів замовлень');
    });

    Route::prefix('/payment-methods')->group(function () {
        Route::get('/', [PaymentMethodController::class, 'index'])->name('payment-methods.index')->middleware('permission:Перегляд методів оплат');
        Route::post('/', [PaymentMethodController::class, 'store'])->name('payment-methods.store')->middleware('permission:Створення методів оплат');
        Route::put('/{id}', [PaymentMethodController::class, 'update'])->name('payment-methods.update')->middleware('permission:Редагування методів оплат');
        Route::delete('/{id}', [PaymentMethodController::class, 'destroy'])->name('payment-methods.destroy')->middleware('permission:Видалення методів оплат');
    });

    Route::prefix('/delivery-methods')->group(function () {
        Route::get('/', [DeliveryMethodController::class, 'index'])->name('delivery-methods.index')->middleware('permission:Перегляд методів доставки');
        Route::post('/', [DeliveryMethodController::class, 'store'])->name('delivery-methods.store')->middleware('permission:Створення методів доставки');
        Route::put('/{id}', [DeliveryMethodController::class, 'update'])->name('delivery-methods.update')->middleware('permission:Редагування методів доставки');
        Route::delete('/{id}', [DeliveryMethodController::class, 'destroy'])->name('delivery-methods.destroy')->middleware('permission:Видалення методів доставки');
    });

    Route::prefix('/groups')->group(function () {
        Route::get('/', [GroupController::class, 'index'])->middleware('permission:Перегляд груп');
        Route::post('/', [GroupController::class, 'store'])->middleware('permission:Створення груп');
        Route::put('/{id}', [GroupController::class, 'update'])->middleware('permission:Редагування груп');
        Route::delete('/{id}', [GroupController::class, 'destroy'])->middleware('permission:Видалення груп');
    
        Route::post('/{id}/users', [GroupController::class, 'addUsers'])->middleware('permission:Призначення груп'); 
        Route::delete('/{id}/users/{userId}', [GroupController::class, 'removeUser'])->middleware('permission:Призначення груп');
    });

    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('users.index')->middleware('permission:Перегляд користувачів');
        Route::post('/', [UserController::class, 'store'])->name('users.store')->middleware('permission:Створення користувачів');
        Route::put('/{user}', [UserController::class, 'update'])->name('users.update')->middleware('permission:Редагування користувачів');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('users.destroy')->middleware('permission:Видалення користувачів');
        Route::put('/{user}/roles', [UserController::class, 'updateRoles'])->name('users.roles.update')->middleware('permission:Призначення ролей');
    });

    Route::resource('roles', RoleController::class);
    Route::resource('permissions', PermissionController::class)->only(['index', 'store', 'destroy']);



    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index')->middleware('permission:Перегляд ролей');
    Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create')->middleware('permission:Створення ролей');
    Route::post('/roles', [RoleController::class, 'store'])->name('roles.store')->middleware('permission:Створення ролей');
    Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit')->middleware('permission:Редагування ролей');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update')->middleware('permission:Редагування ролей');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy')->middleware('permission:Видалення ролей');

    Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index')->middleware('permission:Перегляд дозволів');
    Route::post('/permissions', [PermissionController::class, 'store'])->name('permissions.store')->middleware('permission:Створення дозволів');
    Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy'])->name('permissions.destroy')->middleware('permission:Видалення дозволів');




});


