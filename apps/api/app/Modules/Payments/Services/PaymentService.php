<?php

namespace App\Modules\Payments\Services;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Payments\Models\PaymentRefund;
use App\Modules\Payments\Models\PaymentTransaction;
use App\Modules\Payments\Models\PaymentWebhookEvent;
use App\Shared\Contracts\Payments\PaymentInitiationRequest;
use App\Shared\Contracts\Payments\PaymentRefundRequest;
use App\Shared\Contracts\Payments\PaymentVerificationRequest;
use App\Shared\Events\Payments\PaymentConfirmed;
use App\Shared\Events\Payments\RefundProcessed;
use App\Shared\Exceptions\Orders\InvalidOrderStateException;
use App\Shared\Exceptions\Payments\DuplicatePaymentException;
use App\Shared\Exceptions\Payments\PaymentException;
use App\Shared\Exceptions\Payments\PaymentVerificationMismatchException;
use App\Shared\Support\SensitiveDataRedactor;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(
        protected PaymentGatewayFactory $gatewayFactory,
        protected OrderService $orderService,
        protected GatewayAuditLogger $auditLogger,
    ) {}

    /**
     * Initiate a payment session for an order.
     * Prevents duplicate payment records and never holds DB locks during external gateway calls.
     *
     * @return array{transaction: PaymentTransaction, idempotent_replay: bool}
     */
    public function initiatePayment(
        int|Order $orderOrId,
        string $gateway,
        string $idempotencyKey,
        array $options = []
    ): array {
        $normalizedKey = trim($idempotencyKey);
        if ($normalizedKey === '') {
            throw new PaymentException('An idempotency_key is required to initiate payment.', 422, 'idempotency_key');
        }

        // 1. Fast-path idempotency check
        $existingTx = PaymentTransaction::query()
            ->where('idempotency_key', $normalizedKey)
            ->first();

        if ($existingTx !== null) {
            return [
                'transaction'       => $existingTx,
                'idempotent_replay' => true,
            ];
        }

        $adapter = $this->gatewayFactory->resolve($gateway);
        $gatewayName = $adapter->gatewayName();
        $idempotentReplay = false;

        // 2. Lock Order & create pending PaymentTransaction inside quick local DB transaction
        /** @var array{tx: PaymentTransaction, order: Order} $prepared */
        $prepared = DB::transaction(function () use ($orderOrId, $gatewayName, $normalizedKey, &$idempotentReplay) {
            $lockedByKey = PaymentTransaction::query()
                ->where('idempotency_key', $normalizedKey)
                ->lockForUpdate()
                ->first();

            if ($lockedByKey !== null) {
                $idempotentReplay = true;
                return [
                    'tx'    => $lockedByKey,
                    'order' => $lockedByKey->order,
                ];
            }

            $orderId = $orderOrId instanceof Order ? $orderOrId->id : (int) $orderOrId;
            /** @var Order|null $order */
            $order = Order::query()->where('id', $orderId)->lockForUpdate()->first();
            if (!$order) {
                abort(404, 'Order not found.');
            }

            if ($order->payment_status === 'paid') {
                throw new DuplicatePaymentException(
                    "Order '{$order->order_number}' has already been paid.",
                    ['order_number' => $order->order_number]
                );
            }

            if ($order->status === 'cancelled') {
                throw new InvalidOrderStateException(
                    "Cannot initiate payment for cancelled order '{$order->order_number}'."
                );
            }

            // Check if a paid transaction already exists for this order
            $paidTx = PaymentTransaction::query()
                ->where('order_id', $order->id)
                ->where('status', 'paid')
                ->lockForUpdate()
                ->first();

            if ($paidTx !== null) {
                throw new DuplicatePaymentException(
                    "A verified payment transaction already exists for order '{$order->order_number}'."
                );
            }

            $merchantRef = 'TXN-' . $order->order_number . '-' . strtoupper(Str::random(4));

            $tx = PaymentTransaction::query()->create([
                'order_id'           => $order->id,
                'idempotency_key'    => $normalizedKey,
                'merchant_reference' => $merchantRef,
                'gateway'            => $gatewayName,
                'amount'             => (int) $order->total_amount,
                'refunded_amount'    => 0,
                'currency'           => $order->currency ?? 'BDT',
                'status'             => $gatewayName === 'cod' ? 'cod_pending' : 'pending',
            ]);

            return [
                'tx'    => $tx,
                'order' => $order,
            ];
        });

        if ($idempotentReplay) {
            return [
                'transaction'       => $prepared['tx'],
                'idempotent_replay' => true,
            ];
        }

        $tx = $prepared['tx'];
        $order = $prepared['order'];
        $addr = $order->shipping_address_snapshot ?? [];

        // 3. Call gateway adapter OUTSIDE database transaction (zero DB locks held during network I/O!)
        $initRequest = new PaymentInitiationRequest(
            orderId: $order->id,
            orderNumber: $order->order_number,
            merchantReference: $tx->merchant_reference,
            amountPaisa: (int) $order->total_amount,
            currency: $order->currency ?? 'BDT',
            customerName: (string) ($addr['name'] ?? 'Customer'),
            customerPhone: (string) ($addr['phone'] ?? '01700000000'),
            customerEmail: $addr['email'] ?? null,
            customerAddress: (string) ($addr['street_address'] ?? 'Dhaka'),
            customerCity: (string) ($addr['district'] ?? 'Dhaka'),
            customerPostcode: (string) ($addr['postal_code'] ?? '1212'),
            callbackUrl: $options['callback_url'] ?? null
        );

        $result = $adapter->initiate($initRequest);

        // 4. Persist redacted initiation result
        $tx->update([
            'gateway_payment_id'      => $result->gatewayPaymentId,
            'gateway_payment_url'     => $result->redirectUrl,
            'status'                  => $result->successful ? $result->status : 'failed',
            'failure_reason'          => $result->successful ? null : $result->errorMessage,
            'failed_at'               => $result->successful ? null : now(),
            'raw_initiation_request'  => SensitiveDataRedactor::redact($result->rawRequest),
            'raw_initiation_response' => SensitiveDataRedactor::redact($result->rawResponse),
        ]);

        if (!$result->successful) {
            throw new PaymentException(
                $result->errorMessage ?? "Failed to initiate payment with gateway '{$gatewayName}'.",
                502,
                'gateway'
            );
        }

        return [
            'transaction'       => $tx->fresh() ?? $tx,
            'idempotent_replay' => false,
        ];
    }

    /**
     * Server-side payment verification & idempotent state transition.
     * Validates merchant reference, amount in BDT paisa, and currency ('BDT').
     * Separates payment_status from order status and handles delayed/repeated callbacks safely.
     *
     * @return array{transaction: PaymentTransaction, order: Order, idempotent_replay: bool}
     */
    public function verifyPayment(
        string $referenceOrPaymentId,
        string $gateway,
        array $callbackPayload = [],
        string $actorType = 'system'
    ): array {
        $adapter = $this->gatewayFactory->resolve($gateway);
        $gatewayName = $adapter->gatewayName();

        /** @var PaymentTransaction|null $tx */
        $tx = PaymentTransaction::query()
            ->with('order')
            ->where('gateway', $gatewayName)
            ->where(function ($q) use ($referenceOrPaymentId) {
                $q->where('merchant_reference', $referenceOrPaymentId)
                    ->orWhere('gateway_payment_id', $referenceOrPaymentId)
                    ->orWhere('gateway_transaction_id', $referenceOrPaymentId);
            })
            ->latest('id')
            ->first();

        if (!$tx && !empty($callbackPayload['order_number'])) {
            $orderByNum = Order::query()->where('order_number', $callbackPayload['order_number'])->first();
            if ($orderByNum) {
                $tx = PaymentTransaction::query()
                    ->with('order')
                    ->where('order_id', $orderByNum->id)
                    ->where('gateway', $gatewayName)
                    ->latest('id')
                    ->first();
            }
        }

        if (!$tx || !$tx->order) {
            throw new PaymentException(
                "Payment transaction for reference '{$referenceOrPaymentId}' on gateway '{$gatewayName}' was not found.",
                404,
                'merchant_reference'
            );
        }

        // Repeated callback on an already-paid transaction -> idempotent return!
        if ($tx->status === 'paid') {
            return [
                'transaction'       => $tx,
                'order'             => $tx->order,
                'idempotent_replay' => true,
            ];
        }

        // If callback payload doesn't explicitly override amount_paisa for mock/cod, pass expected for mock default
        if (in_array($gatewayName, ['mock', 'cod'], true) && !isset($callbackPayload['amount_paisa']) && !isset($callbackPayload['simulated_amount_paisa'])) {
            $callbackPayload['amount_paisa'] = (int) $tx->amount;
        }

        // 1. Perform server-to-server verification OUTSIDE DB transaction (no DB locks held during HTTP call!)
        $verificationRequest = new PaymentVerificationRequest(
            merchantReference: $tx->merchant_reference,
            gatewayPaymentId: $tx->gateway_payment_id,
            gatewayTransactionId: $tx->gateway_transaction_id ?? ($callbackPayload['trx_id'] ?? $callbackPayload['val_id'] ?? null),
            callbackPayload: $callbackPayload
        );

        $verification = $adapter->verify($verificationRequest);

        $idempotentReplay = false;
        $confirmedEvent = null;

        // 2. Apply verified result inside atomic DB transaction with row locks
        $result = DB::transaction(function () use (
            $tx,
            $verification,
            $actorType,
            &$idempotentReplay,
            &$confirmedEvent
        ) {
            /** @var PaymentTransaction $lockedTx */
            $lockedTx = PaymentTransaction::query()
                ->where('id', $tx->id)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()
                ->where('id', $lockedTx->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedTx->status === 'paid') {
                $idempotentReplay = true;
                return [
                    'transaction' => $lockedTx,
                    'order'       => $lockedOrder,
                ];
            }

            $redactedReq = SensitiveDataRedactor::redact($verification->rawRequest);
            $redactedRes = SensitiveDataRedactor::redact($verification->rawResponse);

            // Check 1: Did the gateway report payment verified/completed?
            if (!$verification->verified || $verification->status !== 'paid') {
                $lockedTx->update([
                    'status'                    => 'failed',
                    'failure_reason'            => $verification->failureReason ?? 'Gateway verification failed.',
                    'failed_at'                 => now(),
                    'raw_verification_request'  => $redactedReq,
                    'raw_verification_response' => $redactedRes,
                ]);

                if ($lockedOrder->payment_status !== 'paid') {
                    $prevOrderStatus = $lockedOrder->status;
                    $prevPaymentStatus = $lockedOrder->payment_status;
                    $newOrderStatus = in_array($prevOrderStatus, ['pending_payment', 'awaiting_payment'], true)
                        ? 'payment_failed'
                        : $prevOrderStatus;

                    $lockedOrder->update([
                        'status'         => $newOrderStatus,
                        'payment_status' => 'failed',
                    ]);

                    $this->orderService->recordStatusHistory(
                        order: $lockedOrder,
                        fromStatus: $prevOrderStatus,
                        toStatus: $newOrderStatus,
                        fromPaymentStatus: $prevPaymentStatus,
                        toPaymentStatus: 'failed',
                        actorType: $actorType,
                        reason: $verification->failureReason ?? 'Payment verification failed'
                    );
                }

                throw new PaymentException(
                    $verification->failureReason ?? 'Payment verification failed.',
                    422,
                    'payment'
                );
            }

            // Check 2: Validate merchant reference matches transaction or order_number
            $validMerchantRefs = [$lockedTx->merchant_reference, $lockedOrder->order_number];
            if (!in_array($verification->merchantReference, $validMerchantRefs, true)) {
                $lockedTx->update([
                    'status'                    => 'failed',
                    'failure_reason'            => "Merchant reference mismatch: expected '{$lockedTx->merchant_reference}', got '{$verification->merchantReference}'.",
                    'failed_at'                 => now(),
                    'raw_verification_request'  => $redactedReq,
                    'raw_verification_response' => $redactedRes,
                ]);

                throw new PaymentVerificationMismatchException(
                    "Payment merchant reference mismatch: expected '{$lockedTx->merchant_reference}', got '{$verification->merchantReference}'.",
                    [
                        'expected_reference' => $lockedTx->merchant_reference,
                        'actual_reference'   => $verification->merchantReference,
                    ]
                );
            }

            // Check 3: Validate merchant ID if configured and returned by gateway
            $configuredMerchantId = match ($lockedTx->gateway) {
                'nagad'      => (string) config('payment.gateways.nagad.merchant_id', ''),
                'sslcommerz' => (string) config('payment.gateways.sslcommerz.store_id', ''),
                'mock'       => (string) config('payment.gateways.mock.merchant_id', ''),
                default      => '',
            };
            if ($configuredMerchantId !== '' && $verification->merchantId !== null && $verification->merchantId !== '') {
                if (!hash_equals($configuredMerchantId, $verification->merchantId)) {
                    $lockedTx->update([
                        'status'                    => 'failed',
                        'failure_reason'            => 'Merchant ID mismatch during verification.',
                        'failed_at'                 => now(),
                        'raw_verification_request'  => $redactedReq,
                        'raw_verification_response' => $redactedRes,
                    ]);

                    throw new PaymentVerificationMismatchException('Merchant ID mismatch during server-side payment verification.');
                }
            }

            // Check 4: Validate currency matches ('BDT')
            if (strtoupper($verification->currency) !== strtoupper($lockedOrder->currency)) {
                $lockedTx->update([
                    'status'                    => 'failed',
                    'failure_reason'            => "Currency mismatch: expected '{$lockedOrder->currency}', got '{$verification->currency}'.",
                    'failed_at'                 => now(),
                    'raw_verification_request'  => $redactedReq,
                    'raw_verification_response' => $redactedRes,
                ]);

                throw new PaymentVerificationMismatchException(
                    "Payment currency mismatch: expected '{$lockedOrder->currency}', got '{$verification->currency}'.",
                    [
                        'expected_currency' => $lockedOrder->currency,
                        'actual_currency'   => $verification->currency,
                    ]
                );
            }

            // Check 5: Validate exact amount in integer BDT paisa
            if ($verification->amountPaisa !== (int) $lockedOrder->total_amount) {
                $lockedTx->update([
                    'status'                    => 'failed',
                    'failure_reason'            => "Amount mismatch: expected {$lockedOrder->total_amount} paisa, got {$verification->amountPaisa} paisa.",
                    'failed_at'                 => now(),
                    'raw_verification_request'  => $redactedReq,
                    'raw_verification_response' => $redactedRes,
                ]);

                throw new PaymentVerificationMismatchException(
                    "Payment amount mismatch: expected {$lockedOrder->total_amount} paisa, got {$verification->amountPaisa} paisa.",
                    [
                        'expected_amount_paisa' => (int) $lockedOrder->total_amount,
                        'actual_amount_paisa'   => $verification->amountPaisa,
                    ]
                );
            }

            // Check 6: Handle delayed callback arriving AFTER order was already cancelled
            $prevOrderStatus = $lockedOrder->status;
            $prevPaymentStatus = $lockedOrder->payment_status;

            if ($prevOrderStatus === 'cancelled') {
                $metadata = $lockedTx->metadata ?? [];
                $metadata['delayed_callback_on_cancelled_order'] = true;
                $metadata['requires_manual_refund'] = true;

                $lockedTx->update([
                    'status'                    => 'paid',
                    'gateway_payment_id'        => $verification->gatewayPaymentId ?? $lockedTx->gateway_payment_id,
                    'gateway_transaction_id'    => $verification->gatewayTransactionId,
                    'paid_at'                   => now(),
                    'raw_verification_request'  => $redactedReq,
                    'raw_verification_response' => $redactedRes,
                    'metadata'                  => $metadata,
                ]);

                // Update payment_status to 'paid' while keeping order status 'cancelled' (separated statuses!)
                $lockedOrder->update([
                    'payment_status' => 'paid',
                ]);

                $this->orderService->recordStatusHistory(
                    order: $lockedOrder,
                    fromStatus: 'cancelled',
                    toStatus: 'cancelled',
                    fromPaymentStatus: $prevPaymentStatus,
                    toPaymentStatus: 'paid',
                    actorType: $actorType,
                    reason: 'Delayed payment callback verified after order cancellation; flagged for refund',
                    metadata: ['payment_transaction_id' => $lockedTx->id]
                );

                return [
                    'transaction' => $lockedTx->fresh() ?? $lockedTx,
                    'order'       => $lockedOrder->fresh() ?? $lockedOrder,
                ];
            }

            // Normal timely payment confirmation
            $lockedTx->update([
                'status'                    => 'paid',
                'gateway_payment_id'        => $verification->gatewayPaymentId ?? $lockedTx->gateway_payment_id,
                'gateway_transaction_id'    => $verification->gatewayTransactionId,
                'paid_at'                   => now(),
                'raw_verification_request'  => $redactedReq,
                'raw_verification_response' => $redactedRes,
            ]);

            $newOrderStatus = in_array($prevOrderStatus, ['pending', 'pending_payment', 'awaiting_payment', 'payment_failed'], true)
                ? 'confirmed'
                : $prevOrderStatus;

            $lockedOrder->update([
                'status'         => $newOrderStatus,
                'payment_status' => 'paid',
                'confirmed_at'   => $lockedOrder->confirmed_at ?? now(),
            ]);

            $this->orderService->recordStatusHistory(
                order: $lockedOrder,
                fromStatus: $prevOrderStatus,
                toStatus: $newOrderStatus,
                fromPaymentStatus: $prevPaymentStatus,
                toPaymentStatus: 'paid',
                actorType: $actorType,
                reason: "Payment verified via {$lockedTx->gateway}",
                metadata: [
                    'payment_transaction_id' => $lockedTx->id,
                    'gateway_transaction_id' => $verification->gatewayTransactionId,
                ]
            );

            $confirmedEvent = new PaymentConfirmed(
                orderId: $lockedOrder->id,
                orderNumber: $lockedOrder->order_number,
                paymentTransactionId: $lockedTx->id,
                gateway: $lockedTx->gateway,
                gatewayTransactionId: (string) $verification->gatewayTransactionId,
                amountPaisa: (int) $lockedTx->amount,
                currency: $lockedTx->currency
            );

            return [
                'transaction' => $lockedTx->fresh() ?? $lockedTx,
                'order'       => $lockedOrder->fresh() ?? $lockedOrder,
            ];
        });

        if ($confirmedEvent !== null) {
            event($confirmedEvent);
        }

        return [
            'transaction'       => $result['transaction'],
            'order'             => $result['order'],
            'idempotent_replay' => $idempotentReplay,
        ];
    }

    /**
     * Process an incoming payment gateway webhook/IPN idempotently.
     *
     * @return array{webhook_event: PaymentWebhookEvent, transaction: ?PaymentTransaction, order: ?Order, idempotent_replay: bool}
     */
    public function processWebhook(
        string $gateway,
        array $payload,
        array $headers = [],
        string $rawBody = ''
    ): array {
        $adapter = $this->gatewayFactory->resolve($gateway);
        $gatewayName = $adapter->gatewayName();

        $notification = $adapter->parseWebhook($payload, $headers, $rawBody);

        // 1. Check if this gateway_event_id was already processed
        $existingEvent = PaymentWebhookEvent::query()
            ->with(['transaction', 'order'])
            ->where('gateway', $gatewayName)
            ->where('gateway_event_id', $notification->gatewayEventId)
            ->first();

        if ($existingEvent !== null) {
            return [
                'webhook_event'     => $existingEvent,
                'transaction'       => $existingEvent->transaction,
                'order'             => $existingEvent->order,
                'idempotent_replay' => true,
            ];
        }

        // 2. Verify payment server-side using the reference from the webhook
        $lookupReference = $notification->merchantReference !== ''
            ? $notification->merchantReference
            : (string) ($notification->gatewayPaymentId ?? $notification->gatewayTransactionId);

        $verified = $this->verifyPayment(
            referenceOrPaymentId: $lookupReference,
            gateway: $gatewayName,
            callbackPayload: $payload,
            actorType: 'webhook'
        );

        // 3. Record the webhook event idempotently with redacted payload & headers
        try {
            $webhookEvent = PaymentWebhookEvent::query()->create([
                'gateway'                => $gatewayName,
                'gateway_event_id'       => $notification->gatewayEventId,
                'payment_transaction_id' => $verified['transaction']->id,
                'order_id'               => $verified['order']->id,
                'event_type'             => $notification->eventType,
                'status'                 => 'processed',
                'payload'                => SensitiveDataRedactor::redact($payload),
                'headers'                => SensitiveDataRedactor::redact($headers),
                'processed_at'           => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            $webhookEvent = PaymentWebhookEvent::query()
                ->where('gateway', $gatewayName)
                ->where('gateway_event_id', $notification->gatewayEventId)
                ->firstOrFail();

            return [
                'webhook_event'     => $webhookEvent,
                'transaction'       => $verified['transaction'],
                'order'             => $verified['order'],
                'idempotent_replay' => true,
            ];
        }

        return [
            'webhook_event'     => $webhookEvent,
            'transaction'       => $verified['transaction'],
            'order'             => $verified['order'],
            'idempotent_replay' => $verified['idempotent_replay'],
        ];
    }

    /**
     * Initiate a full or partial refund for a paid order.
     */
    public function refundOrder(
        int|Order $orderOrId,
        int $refundAmountPaisa,
        string $reason,
        string $idempotencyKey,
        ?int $actorId = null
    ): PaymentRefund {
        $normalizedKey = trim($idempotencyKey);
        if ($normalizedKey === '') {
            throw new PaymentException('An idempotency_key is required for refunds.', 422, 'idempotency_key');
        }

        if ($refundAmountPaisa <= 0) {
            throw new PaymentException('Refund amount must be greater than zero.', 422, 'amount');
        }

        $existingRefund = PaymentRefund::query()
            ->where('idempotency_key', $normalizedKey)
            ->first();

        if ($existingRefund !== null) {
            return $existingRefund;
        }

        $orderId = $orderOrId instanceof Order ? $orderOrId->id : (int) $orderOrId;
        /** @var Order|null $order */
        $order = Order::query()->find($orderId);
        if (!$order) {
            abort(404, 'Order not found.');
        }

        /** @var PaymentTransaction|null $tx */
        $tx = PaymentTransaction::query()
            ->where('order_id', $order->id)
            ->whereIn('status', ['paid', 'partially_refunded'])
            ->latest('id')
            ->first();

        if (!$tx) {
            throw new InvalidOrderStateException(
                "Order '{$order->order_number}' does not have a refundable paid transaction."
            );
        }

        $remainingRefundable = (int) $tx->amount - (int) $tx->refunded_amount;
        if ($refundAmountPaisa > $remainingRefundable) {
            throw new PaymentException(
                "Refund amount ({$refundAmountPaisa} paisa) exceeds remaining refundable balance ({$remainingRefundable} paisa).",
                422,
                'amount'
            );
        }

        $adapter = $this->gatewayFactory->resolve($tx->gateway);

        // Execute gateway refund call OUTSIDE DB transaction
        $refundResult = $adapter->refund(new PaymentRefundRequest(
            orderId: $order->id,
            orderNumber: $order->order_number,
            merchantReference: $tx->merchant_reference,
            gatewayPaymentId: (string) ($tx->gateway_payment_id ?? $tx->merchant_reference),
            gatewayTransactionId: (string) ($tx->gateway_transaction_id ?? $tx->merchant_reference),
            refundAmountPaisa: $refundAmountPaisa,
            currency: $tx->currency,
            reason: $reason
        ));

        if (!$refundResult->successful) {
            throw new PaymentException(
                $refundResult->errorMessage ?? 'Gateway refund request failed.',
                502,
                'refund'
            );
        }

        $refundEvent = null;

        $refund = DB::transaction(function () use (
            $order,
            $tx,
            $refundAmountPaisa,
            $reason,
            $normalizedKey,
            $actorId,
            $refundResult,
            &$refundEvent
        ) {
            $lockedTx = PaymentTransaction::query()
                ->where('id', $tx->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedOrder = Order::query()
                ->where('id', $order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $newRefundedTotal = (int) $lockedTx->refunded_amount + $refundAmountPaisa;
            $newPaymentStatus = $newRefundedTotal >= (int) $lockedTx->amount
                ? 'refunded'
                : 'partially_refunded';

            $createdRefund = PaymentRefund::query()->create([
                'payment_transaction_id' => $lockedTx->id,
                'order_id'               => $lockedOrder->id,
                'idempotency_key'        => $normalizedKey,
                'gateway'                => $lockedTx->gateway,
                'gateway_refund_id'      => $refundResult->gatewayRefundId,
                'amount'                 => $refundAmountPaisa,
                'currency'               => $lockedTx->currency,
                'status'                 => 'completed',
                'reason'                 => $reason,
                'initiated_by'           => $actorId,
                'raw_request'            => SensitiveDataRedactor::redact($refundResult->rawRequest),
                'raw_response'           => SensitiveDataRedactor::redact($refundResult->rawResponse),
                'refunded_at'            => now(),
            ]);

            $lockedTx->update([
                'refunded_amount' => $newRefundedTotal,
                'status'          => $newPaymentStatus,
                'refunded_at'     => now(),
            ]);

            $prevPaymentStatus = $lockedOrder->payment_status;
            $lockedOrder->update([
                'payment_status' => $newPaymentStatus,
            ]);

            $this->orderService->recordStatusHistory(
                order: $lockedOrder,
                fromStatus: $lockedOrder->status,
                toStatus: $lockedOrder->status,
                fromPaymentStatus: $prevPaymentStatus,
                toPaymentStatus: $newPaymentStatus,
                actorType: 'staff',
                actorId: $actorId,
                reason: "Refund processed: {$reason}",
                metadata: [
                    'payment_refund_id'   => $createdRefund->id,
                    'refund_amount_paisa' => $refundAmountPaisa,
                ]
            );

            $refundEvent = new RefundProcessed(
                orderId: $lockedOrder->id,
                orderNumber: $lockedOrder->order_number,
                paymentRefundId: $createdRefund->id,
                gateway: $lockedTx->gateway,
                amountPaisa: $refundAmountPaisa,
                currency: $lockedTx->currency
            );

            return $createdRefund;
        });

        if ($refundEvent !== null) {
            event($refundEvent);
        }

        return $refund;
    }
}

