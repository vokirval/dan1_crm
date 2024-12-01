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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_status_id')->nullable()->constrained('order_statuses')->onDelete('set null');
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->onDelete('set null');
            $table->foreignId('delivery_method_id')->nullable()->constrained('delivery_methods')->onDelete('set null');
            $table->foreignId('group_id')->nullable()->constrained('groups')->onDelete('set null');
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->onDelete('set null'); // Ответственный пользователь
            
            $table->decimal('delivery_price', 10, 2)->nullable();
            $table->string('delivery_fullname')->nullable();
            $table->string('delivery_address')->nullable();
            $table->string('delivery_second_address')->nullable();
            $table->string('delivery_postcode')->nullable();
            $table->string('delivery_city')->nullable();
            $table->string('delivery_state')->nullable();
            $table->string('delivery_country_code')->nullable();
            
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            
            $table->ipAddress('ip'); // Поддержка ipv4 и ipv6
            $table->text('comment')->nullable();
            $table->string('website_referrer')->nullable(); // URL реферера
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('utm_campaign')->nullable();

            for ($i = 1; $i <= 10; $i++) {
                $table->string('sub_id' . $i)->nullable();
            }

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
