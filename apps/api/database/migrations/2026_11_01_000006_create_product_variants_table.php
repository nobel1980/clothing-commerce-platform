<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 191)->unique();

            // Shorthand denormalized color/size for quick filtering (actual attributes in pivot)
            $table->string('color', 100)->nullable();
            $table->string('size', 50)->nullable();

            // Pricing — stored in integer subunits (paisa/cents) per AGENTS.md
            $table->unsignedBigInteger('price_amount');          // selling price
            $table->unsignedBigInteger('compare_at_price')->nullable(); // original / compare-at price
            $table->unsignedBigInteger('cost_price')->nullable();
            $table->string('currency', 3)->default('BDT');

            $table->string('status', 30)->default('active'); // active, inactive, discontinued

            // Note: stock_quantity lives in Inventory module — never duplicated here
            $table->timestamps();
            $table->softDeletes();

            $table->index('product_id');
            $table->index('sku');
            $table->index(['product_id', 'status']);
        });

        Schema::create('variant_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->constrained('attribute_values')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['product_variant_id', 'attribute_id'], 'vav_unique_variant_attribute');
            $table->index('product_variant_id');
            $table->index('attribute_value_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_attribute_values');
        Schema::dropIfExists('product_variants');
    }
};
