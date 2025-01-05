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
        Schema::create('email_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('template_id')->constrained('email_templates')->onDelete('cascade');
            $table->string('to_email'); // Email получателя
            $table->string('subject'); // Тема письма
            $table->text('body'); // Текст письма
            $table->enum('status', ['success', 'failed']); // Статус отправки
            $table->text('error_message')->nullable(); // Сообщение об ошибке
            $table->timestamp('sent_at')->nullable(); // Время отправки
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_history');
    }
};
