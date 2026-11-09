<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')
                ->constrained('product_variants')
                ->cascadeOnDelete();
            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->cascadeOnDelete();

            $table->integer('quantity_on_hand')->default(0);
            $table->integer('quantity_reserved')->default(0);
            $table->integer('low_stock_threshold')->default(5);
            $table->integer('reorder_point')->default(10)->nullable();
            $table->integer('reorder_quantity')->default(50)->nullable();
            $table->string('bin_location', 100)->nullable();
            $table->timestamp('low_stock_notified_at')->nullable();
            $table->timestamps();

            $table->unique(['product_variant_id', 'warehouse_id'], 'inv_items_variant_warehouse_unique');
            $table->index('product_variant_id', 'inv_items_variant_idx');
            $table->index('warehouse_id', 'inv_items_warehouse_idx');
            $table->index(
                ['quantity_on_hand', 'quantity_reserved', 'low_stock_threshold'],
                'inv_items_stock_levels_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};

