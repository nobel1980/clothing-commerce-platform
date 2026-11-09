<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key', 191)->unique();
            $table->string('reference_type', 100)->nullable();
            $table->string('reference_id', 191)->nullable();
            $table->string('status', 30)->default('active'); // active, committed, released, expired
            $table->timestamp('expires_at');
            $table->timestamp('committed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at'], 'stock_res_status_expires_idx');
            $table->index(['reference_type', 'reference_id'], 'stock_res_reference_idx');
            $table->index('status', 'stock_res_status_idx');
        });

        Schema::create('stock_reservation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_reservation_id')
                ->constrained('stock_reservations')
                ->cascadeOnDelete();
            $table->foreignId('inventory_item_id')
                ->constrained('inventory_items')
                ->cascadeOnDelete();
            $table->foreignId('product_variant_id')
                ->constrained('product_variants')
                ->cascadeOnDelete();
            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->unique(['stock_reservation_id', 'inventory_item_id'], 'stock_res_items_res_inv_unique');
            $table->index('stock_reservation_id', 'stock_res_items_res_idx');
            $table->index('inventory_item_id', 'stock_res_items_inv_idx');
            $table->index(['product_variant_id', 'warehouse_id'], 'stock_res_items_variant_wh_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservation_items');
        Schema::dropIfExists('stock_reservations');
    }
};

