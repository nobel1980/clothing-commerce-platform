<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('guest_token', 100)->nullable();
            $table->string('status', 32)->default('active'); // active, merged, converted, abandoned
            $table->string('currency', 3)->default('BDT');
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->string('coupon_code', 64)->nullable();
            $table->foreignId('shipping_address_id')->nullable()->constrained('addresses')->nullOnDelete();
            $table->json('shipping_address_data')->nullable();
            $table->json('billing_address_data')->nullable();
            $table->string('shipping_method', 32)->default('standard');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('merged_into_cart_id')->nullable();
            $table->timestamp('merged_at')->nullable();
            $table->unsignedBigInteger('converted_order_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['guest_token', 'status']);
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_snapshot');
            $table->string('currency', 3)->default('BDT');
            $table->timestamps();

            $table->unique(['cart_id', 'product_variant_id']);
            $table->index(['product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};

