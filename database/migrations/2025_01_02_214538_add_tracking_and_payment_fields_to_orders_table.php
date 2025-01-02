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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('tracking_number')->nullable()->after('comment'); // Поле для номера отслеживания
            $table->boolean('is_paid')->default(false)->after('tracking_number'); // Флаг оплаты
            $table->decimal('paid_amount', 10, 2)->nullable()->after('is_paid'); // Оплаченная сумма
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('tracking_number');
            $table->dropColumn('is_paid');
            $table->dropColumn('paid_amount');
        });
    }
};
