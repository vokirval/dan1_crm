<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('logs', function (Blueprint $table) {
            $table->boolean('completed')->default(false)->after('message');
            $table->foreignId('completed_by')->nullable()->constrained('users')->onDelete('set null')->after('completed');
        });
    }

    public function down(): void
    {
        Schema::table('logs', function (Blueprint $table) {
            $table->dropForeign(['completed_by']);
            $table->dropColumn(['completed', 'completed_by']);
        });
    }
};