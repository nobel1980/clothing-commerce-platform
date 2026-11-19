<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::defaultStringLength(191);

        Schema::table('orders', function (Blueprint $table) {
            $table->index(['created_at', 'status'], 'orders_created_at_status_idx');
            $table->index(['payment_method', 'status'], 'orders_payment_method_status_idx');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->index(['product_id', 'order_id'], 'order_items_product_id_order_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('order_items_product_id_order_id_idx');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_created_at_status_idx');
            $table->dropIndex('orders_payment_method_status_idx');
        });
    }
};
