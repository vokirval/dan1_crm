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
        Schema::create('group_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->onDelete('restrict'); // Группу нельзя удалить, если есть связанные пользователи
            $table->foreignId('user_id')->constrained()->onDelete('restrict');  // Пользователя нельзя удалить, если он в группе
            $table->timestamps(); // Для отслеживания времени добавления
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_user');
    }
};
