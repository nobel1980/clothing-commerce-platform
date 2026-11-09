<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')
                ->constrained('inventory_items')
                ->cascadeOnDelete();
            $table->foreignId('product_variant_id')
                ->constrained('product_variants')
                ->cascadeOnDelete();
            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->cascadeOnDelete();

            $table->string('type', 50);
            $table->integer('quantity_change');
            $table->integer('on_hand_delta')->default(0);
            $table->integer('reserved_delta')->default(0);
            $table->integer('quantity_before');
            $table->integer('quantity_after');
            $table->integer('reserved_before')->default(0);
            $table->integer('reserved_after')->default(0);

            $table->string('reference_type', 100)->nullable();
            $table->string('reference_id', 191)->nullable();
            $table->string('reason', 191)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('performed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('inventory_item_id', 'inv_mov_item_idx');
            $table->index(['product_variant_id', 'warehouse_id'], 'inv_mov_variant_warehouse_idx');
            $table->index('type', 'inv_mov_type_idx');
            $table->index(['reference_type', 'reference_id'], 'inv_mov_reference_idx');
            $table->index('created_at', 'inv_mov_created_at_idx');
            $table->index('performed_by', 'inv_mov_performed_by_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};

