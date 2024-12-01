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
        Schema::create('product_variations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('sku')->unique(); // Уникальный SKU
            $table->decimal('price', 10, 2); // Цена
            $table->decimal('discounted_price', 10, 2)->nullable(); // Цена со скидкой
            $table->decimal('cost', 10, 2); // Себестоимость
            $table->integer('stock')->default(0); // Сток
            $table->enum('status', ['active', 'inactive'])->default('active'); // Статус
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variations');
    }
};
