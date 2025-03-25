<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrderLockController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\OrderStatusController;
use App\Http\Controllers\EmailTemplateController;

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

use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\DeliveryMethodController;
use App\Http\Controllers\ProductsCategoryController;

use App\Http\Controllers\StatisticController;


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

    Route::post('/orders/{order}/lock', [OrderLockController::class, 'lock']);
    Route::post('/orders/{order}/unlock', [OrderLockController::class, 'unlock']);
    Route::post('/orders/{order}/heartbeat', [OrderLockController::class, 'heartbeat']);
    Route::get('/orders/locked', [OrderLockController::class, 'getLockedOrders']);

    
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/statistics', [StatisticController::class, 'index']);
    Route::post('/statistics/filter', [StatisticController::class, 'filter']);

    Route::prefix('/products')->group(function(){

        //products
        Route::get('/', [ProductsController::class, 'index'])->middleware('permission:Перегляд продуктів');
        Route::get('/getall', [ProductsController::class, 'getAll'])->middleware('permission:Перегляд продуктів');
        Route::get('/{id}/variations', [ProductsController::class, 'getVariations'])->middleware('permission:Перегляд продуктів');

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
        
        Route::post('/{id}/create-inpost', [OrdersController::class, 'createInpostOrder'])->name('orders.createInpost');
        Route::post('/{id}/duplicate', [OrdersController::class, 'duplicateOrder'])->name('orders.duplicate');


    });

    Route::prefix('/order-statuses')->group(function () {
        Route::get('/', [OrderStatusController::class, 'index'])->name('order-statuses.index')->middleware('permission:Перегляд статусів замовлень');
        Route::post('/', [OrderStatusController::class, 'store'])->name('order-statuses.store')->middleware('permission:Створення статусів замовлень');
        Route::put('/{id}', [OrderStatusController::class, 'update'])->name('order-statuses.update')->middleware('permission:Редагування статусів замовлень');
        Route::delete('/{id}', [OrderStatusController::class, 'destroy'])->name('order-statuses.destroy')->middleware('permission:Видалення статусів замовлень');
    });

    Route::prefix('/payment-methods')->group(function () {
        Route::get('/', [PaymentMethodController::class, 'index'])->name('payment-methods.index')->middleware('permission:Перегляд методів оплат');
        Route::get('/getall', action: [PaymentMethodController::class, 'getall'])->name('payment-methods.getall')->middleware('permission:Перегляд методів оплат');
        Route::post('/', [PaymentMethodController::class, 'store'])->name('payment-methods.store')->middleware('permission:Створення методів оплат');
        Route::put('/{id}', [PaymentMethodController::class, 'update'])->name('payment-methods.update')->middleware('permission:Редагування методів оплат');
        Route::delete('/{id}', [PaymentMethodController::class, 'destroy'])->name('payment-methods.destroy')->middleware('permission:Видалення методів оплат');
    });

    Route::prefix('/delivery-methods')->group(function () {
        Route::get('/', [DeliveryMethodController::class, 'index'])->name('delivery-methods.index')->middleware('permission:Перегляд методів доставки');
        Route::get('/getall', [DeliveryMethodController::class, 'getall'])->name('delivery-methods.getall')->middleware('permission:Перегляд методів доставки');
        Route::post('/', [DeliveryMethodController::class, 'store'])->name('delivery-methods.store')->middleware('permission:Створення методів доставки');
        Route::put('/{id}', [DeliveryMethodController::class, 'update'])->name('delivery-methods.update')->middleware('permission:Редагування методів доставки');
        Route::delete('/{id}', [DeliveryMethodController::class, 'destroy'])->name('delivery-methods.destroy')->middleware('permission:Видалення методів доставки');
    });

    Route::prefix('/groups')->group(function () {
        Route::get('/', [GroupController::class, 'index'])->middleware('permission:Перегляд груп');
        Route::get('/getall', [GroupController::class, 'getall'])->middleware('permission:Перегляд груп');
        Route::post('/', [GroupController::class, 'store'])->middleware('permission:Створення груп');
        Route::put('/{id}', [GroupController::class, 'update'])->middleware('permission:Редагування груп');
        Route::delete('/{id}', [GroupController::class, 'destroy'])->middleware('permission:Видалення груп');
    
        Route::post('/{id}/users', [GroupController::class, 'addUsers'])->middleware('permission:Призначення груп'); 
        Route::delete('/{id}/users/{userId}', [GroupController::class, 'removeUser'])->middleware('permission:Призначення груп');
    });

    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('users.index')->middleware('permission:Перегляд користувачів');
        Route::get('/getall', [UserController::class, 'getall'])->name('users.getall')->middleware('permission:Перегляд користувачів');
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

  

    Route::prefix('/email-templates')->group(function () {
        Route::get('/', [EmailTemplateController::class, 'index'])
            ->name('email-templates.index');
            //->middleware('permission:Перегляд шаблонів email');
        Route::get('/create', [EmailTemplateController::class, 'create'])
            ->name('email-templates.create');
            //->middleware('permission:Створення шаблонів email');
        Route::post('/', [EmailTemplateController::class, 'store'])
            ->name('email-templates.store');
            //->middleware('permission:Створення шаблонів email');
        Route::get('/{id}/edit', [EmailTemplateController::class, 'edit'])
            ->name('email-templates.edit');
            //->middleware('permission:Редагування шаблонів email');
        Route::put('/{id}', [EmailTemplateController::class, 'update'])
            ->name('email-templates.update');
            //->middleware('permission:Редагування шаблонів email');
        Route::delete('/{id}', [EmailTemplateController::class, 'destroy'])
            ->name('email-templates.destroy');
            //->middleware('permission:Видалення шаблонів email');
    });

    
    Route::post('/orders/{order}/send-email', [EmailController::class, 'sendEmail'])->name('orders.send-email');
    Route::post('/orders/{order}/preview-template', [EmailController::class, 'previewTemplate'])->name('orders.preview-email');
    Route::get('/email/macros', [EmailController::class, 'getMacrosList'])->name('email.get-macros');

    Route::post('/orders/mass-delete', [OrdersController::class, 'massDelete'])->name('orders.massDelete');
    Route::post('/orders/mass-update-status', [OrdersController::class, 'massUpdateStatus'])->name('orders.massUpdateStatus');
    Route::post('/orders/mass-update-comment', [OrdersController::class, 'massUpdateComment'])->name('orders.massUpdateComment');



});


