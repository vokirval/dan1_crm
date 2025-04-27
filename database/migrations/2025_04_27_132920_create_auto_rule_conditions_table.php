<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('auto_rule_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auto_rule_id')->constrained()->onDelete('cascade');
            $table->string('field'); // Поле заказа, например, 'phone', 'email'
            $table->string('operator'); // Оператор: contains, equals, etc.
            $table->string('value'); // Значение для сравнения
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auto_rule_conditions');
    }
};