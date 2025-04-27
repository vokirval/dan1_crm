<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('auto_rule_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auto_rule_id')->constrained()->onDelete('cascade');
            $table->string('type'); // Тип действия, например, 'log'
            $table->json('parameters')->nullable(); // Параметры действия
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auto_rule_actions');
    }
};