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
        Schema::table('products', function (Blueprint $table) {
            $table->string('short_name')->nullable()->after('name');
            $table->integer('weight')->nullable()->after('discounted_price'); // вес в кг
            $table->integer('length')->nullable()->after('weight');  // длина в мм
            $table->integer('width')->nullable()->after('length');   // ширина в мм
            $table->integer('height')->nullable()->after('width');   // высота в мм
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'short_name',
                'weight',
                'length',
                'width',
                'height'
            ]);
        });
    }
};
