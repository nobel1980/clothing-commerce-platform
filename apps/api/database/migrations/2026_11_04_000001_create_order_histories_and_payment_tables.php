<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('fulfilment_status', 32)->default('unfulfilled')->after('payment_status');
            $table->timestamp('confirmed_at')->nullable()->after('placed_at');
            $table->timestamp('shipped_at')->nullable()->after('confirmed_at');
            $table->timestamp('delivered_at')->nullable()->after('shipped_at');
            $table->timestamp('cancelled_at')->nullable()->after('delivered_at');
            $table->string('cancellation_reason', 191)->nullable()->after('cancelled_at');

            $table->index(['fulfilment_status']);
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('from_payment_status', 32)->nullable();
            $table->string('to_payment_status', 32)->nullable();
            $table->string('from_fulfilment_status', 32)->nullable();
            $table->string('to_fulfilment_status', 32)->nullable();
            $table->string('actor_type', 32)->default('system'); // system, customer, staff, webhook
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('reason', 191)->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id', 'created_at']);
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('idempotency_key', 128)->unique();
            $table->string('merchant_reference', 64)->unique();
            $table->string('gateway', 32); // cod, bkash, nagad, sslcommerz, mock
            $table->string('gateway_payment_id', 128)->nullable();
            $table->string('gateway_transaction_id', 128)->nullable();
            $table->string('gateway_payment_url', 500)->nullable();
            $table->unsignedBigInteger('amount'); // in BDT integer paisa
            $table->unsignedBigInteger('refunded_amount')->default(0);
            $table->string('currency', 3)->default('BDT');
            $table->string('status', 32)->default('pending'); // pending, cod_pending, paid, failed, partially_refunded, refunded
            $table->string('failure_reason', 191)->nullable();
            $table->json('raw_initiation_request')->nullable();
            $table->json('raw_initiation_response')->nullable();
            $table->json('raw_verification_request')->nullable();
            $table->json('raw_verification_response')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['gateway', 'gateway_payment_id']);
            $table->index(['gateway', 'gateway_transaction_id']);
        });

        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 32);
            $table->string('gateway_event_id', 128);
            $table->foreignId('payment_transaction_id')->nullable()->constrained('payment_transactions')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('event_type', 64)->nullable();
            $table->string('status', 32)->default('processed');
            $table->json('payload');
            $table->json('headers')->nullable();
            $table->timestamp('processed_at');
            $table->timestamps();

            $table->unique(['gateway', 'gateway_event_id']);
            $table->index(['order_id']);
        });

        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_transaction_id')->constrained('payment_transactions')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('idempotency_key', 128)->unique();
            $table->string('gateway', 32);
            $table->string('gateway_refund_id', 128)->nullable();
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('BDT');
            $table->string('status', 32)->default('pending'); // pending, completed, failed
            $table->string('reason', 191);
            $table->unsignedBigInteger('initiated_by')->nullable();
            $table->json('raw_request')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['gateway', 'gateway_refund_id']);
        });

        Schema::create('payment_gateway_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('payment_transaction_id')->nullable();
            $table->string('gateway', 32);
            $table->string('operation', 64); // grant_token, initiate, verify, webhook, refund
            $table->string('http_method', 16);
            $table->string('endpoint', 191);
            $table->integer('response_status')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id', 'created_at']);
            $table->index(['payment_transaction_id', 'created_at']);
            $table->index(['gateway', 'operation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_logs');
        Schema::dropIfExists('payment_refunds');
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('order_status_histories');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['fulfilment_status']);
            $table->dropColumn([
                'fulfilment_status',
                'confirmed_at',
                'shipped_at',
                'delivered_at',
                'cancelled_at',
                'cancellation_reason',
            ]);
        });
    }
};

