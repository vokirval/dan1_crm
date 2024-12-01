<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->unique(); // Уникальный SKU для продукта
            $table->text('description')->nullable();
            $table->enum('type', ['simple', 'variable']);
            $table->decimal('price', 10, 2); // Основная цена
            $table->decimal('discounted_price', 10, 2)->nullable(); // Цена со скидкой
            $table->decimal('cost', 10, 2); // Себестоимость
            $table->integer('stock')->default(0); // Сток для простых товаров
            $table->enum('status', ['active', 'inactive'])->default('active'); // Статус
            $table->foreignId('category_id')->nullable()->constrained()->onDelete('set null'); // Категория
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
