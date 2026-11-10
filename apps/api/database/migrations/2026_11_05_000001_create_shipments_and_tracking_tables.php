<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('idempotency_key', 128)->unique();
            $table->string('courier', 32); // pathao, steadfast, redx
            $table->string('consignment_id', 128)->nullable();
            $table->string('merchant_order_reference', 64);
            $table->string('tracking_number', 128)->nullable();
            $table->string('tracking_url', 500)->nullable();
            $table->string('status', 32)->default('pending'); // pending, created, picked_up, in_transit, out_for_delivery, delivered, failed, returning, returned, cancelled
            $table->unsignedBigInteger('shipping_charge')->default(0); // integer BDT paisa
            $table->unsignedBigInteger('cod_amount')->default(0); // integer BDT paisa
            $table->unsignedBigInteger('cod_collected_amount')->default(0); // integer BDT paisa
            $table->string('cod_reconciliation_status', 32)->default('not_applicable'); // not_applicable, pending, reconciled, discrepancy
            $table->timestamp('cod_reconciled_at')->nullable();
            $table->string('currency', 3)->default('BDT');
            $table->unsignedInteger('weight_grams')->default(500);
            $table->unsignedInteger('item_quantity')->default(1);
            $table->json('recipient_snapshot')->nullable();
            $table->json('courier_metadata')->nullable();
            $table->string('failure_reason', 191)->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('inventory_restored_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['courier', 'consignment_id']);
            $table->index(['order_id', 'status']);
            $table->index(['courier', 'status']);
            $table->index(['cod_reconciliation_status']);
        });

        Schema::create('shipment_tracking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->string('courier', 32);
            $table->string('consignment_id', 128)->nullable();
            $table->string('event_key', 128);
            $table->string('external_status', 64);
            $table->string('internal_status', 32);
            $table->string('description', 191)->nullable();
            $table->string('location', 191)->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['shipment_id', 'event_key']);
            $table->index(['shipment_id', 'occurred_at']);
        });

        Schema::create('courier_api_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained('shipments')->nullOnDelete();
            $table->string('courier', 32);
            $table->string('operation', 64); // issue_token, calculate_charge, create_shipment, cancel_shipment, track_shipment, webhook
            $table->string('http_method', 16);
            $table->string('endpoint', 191);
            $table->integer('response_status')->nullable();
            $table->unsignedSmallInteger('attempts')->default(1);
            $table->boolean('is_error')->default(false);
            $table->string('error_message', 191)->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['courier', 'operation']);
            $table->index(['courier', 'is_error']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_api_logs');
        Schema::dropIfExists('shipment_tracking_events');
        Schema::dropIfExists('shipments');
    }
};

