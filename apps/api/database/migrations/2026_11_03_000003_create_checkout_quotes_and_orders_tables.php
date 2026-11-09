<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_quotes', function (Blueprint $table) {
            $table->id();
            $table->string('quote_token', 64)->unique();
            $table->foreignId('cart_id')->nullable()->constrained('carts')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guest_token', 100)->nullable();
            $table->string('status', 32)->default('active'); // active, converted, expired
            $table->string('currency', 3)->default('BDT');
            $table->string('shipping_method', 32)->default('standard');
            $table->string('payment_method', 32)->default('cod');
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->string('coupon_code', 64)->nullable();
            $table->unsignedBigInteger('subtotal_amount');
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('shipping_amount')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('total_amount');
            $table->json('items_snapshot');
            $table->json('shipping_address_snapshot')->nullable();
            $table->json('billing_address_snapshot')->nullable();
            $table->json('coupon_snapshot')->nullable();
            $table->string('pricing_hash', 64);
            $table->timestamp('expires_at');
            $table->unsignedBigInteger('converted_order_id')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index(['user_id', 'status']);
            $table->index(['guest_token', 'status']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 64)->unique();
            $table->string('idempotency_key', 128)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guest_token', 100)->nullable();
            $table->foreignId('cart_id')->nullable()->constrained('carts')->nullOnDelete();
            $table->foreignId('checkout_quote_id')->nullable()->constrained('checkout_quotes')->nullOnDelete();
            $table->foreignId('stock_reservation_id')->nullable()->constrained('stock_reservations')->nullOnDelete();
            $table->string('status', 32)->default('confirmed'); // confirmed, pending_payment, processing, shipped, delivered, cancelled
            $table->string('payment_method', 32); // cod, online, card, bkash, nagad
            $table->string('payment_status', 32)->default('pending'); // cod_pending, awaiting_payment, paid, failed, refunded
            $table->string('currency', 3)->default('BDT');
            $table->unsignedBigInteger('subtotal_amount');
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('shipping_amount')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('total_amount');
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->string('coupon_code', 64)->nullable();
            $table->json('coupon_snapshot')->nullable();
            $table->string('shipping_method', 32)->default('standard');
            $table->json('shipping_address_snapshot');
            $table->json('billing_address_snapshot')->nullable();
            $table->json('payment_preparation_data')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('placed_at');
            $table->timestamps();

            $table->index(['user_id', 'placed_at']);
            $table->index(['guest_token', 'placed_at']);
            $table->index(['status', 'payment_status']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('sku_snapshot', 100);
            $table->string('product_name_snapshot', 191);
            $table->string('brand_name_snapshot', 191)->nullable();
            $table->string('color_snapshot', 100)->nullable();
            $table->string('size_snapshot', 100)->nullable();
            $table->string('image_url_snapshot', 191)->nullable();
            $table->json('attributes_snapshot')->nullable();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_amount');
            $table->unsignedBigInteger('compare_at_price_amount')->nullable();
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('line_total_amount');
            $table->string('currency', 3)->default('BDT');
            $table->timestamps();

            $table->index(['order_id', 'product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('checkout_quotes');
    }
};

