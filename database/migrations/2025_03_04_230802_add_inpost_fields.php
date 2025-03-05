<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('inpost_id')->nullable()->comment('ID заказа в InPost');
            $table->string('return_tracking_number')->nullable()->comment('Возвратный трекинг-номер');
            $table->string('inpost_status')->nullable()->comment('Статус заказа в InPost');
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['inpost_id', 'return_tracking_number', 'inpost_status']);
        });
    }
};
