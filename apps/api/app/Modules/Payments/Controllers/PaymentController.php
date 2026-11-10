<?php

namespace App\Modules\Payments\Controllers;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Payments\Models\PaymentTransaction;
use App\Modules\Payments\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService,
        protected OrderService $orderService,
    ) {}

    /**
     * POST /api/payments/initiate
     * Initiates a payment transaction with the selected gateway.
     */
    public function initiate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_number'    => ['required_without:order_id', 'nullable', 'string', 'max:40'],
            'order_id'        => ['required_without:order_number', 'nullable', 'integer', 'min:1'],
            'gateway'         => ['required', 'string', Rule::in(['cod', 'bkash', 'nagad', 'sslcommerz', 'mock'])],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
            'callback_url'    => ['nullable', 'url', 'max:500'],
        ]);

        $order = !empty($validated['order_id'])
            ? Order::query()->findOrFail((int) $validated['order_id'])
            : Order::query()->where('order_number', $validated['order_number'])->firstOrFail();

        $idempotencyKey = $request->header('Idempotency-Key')
            ?? $request->header('X-Idempotency-Key')
            ?? $validated['idempotency_key']
            ?? ('pay-init-' . $order->order_number . '-' . $validated['gateway']);

        $result = $this->paymentService->initiatePayment(
            orderOrId: $order,
            gateway: (string) $validated['gateway'],
            idempotencyKey: (string) $idempotencyKey,
            options: [
                'callback_url' => $validated['callback_url'] ?? null,
            ]
        );

        /** @var PaymentTransaction $tx */
        $tx = $result['transaction'];
        $statusCode = $result['idempotent_replay'] ? 200 : 201;

        return response()->json([
            'data' => [
                'transaction' => $this->formatTransaction($tx),
                'redirect_url' => $tx->gateway_payment_url,
            ],
            'idempotent_replay' => $result['idempotent_replay'],
        ], $statusCode);
    }

    /**
     * POST /api/payments/verify
     * Server-side verification of a payment transaction.
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'gateway'            => ['required', 'string', Rule::in(['cod', 'bkash', 'nagad', 'sslcommerz', 'mock'])],
            'merchant_reference' => ['required_without:payment_id', 'nullable', 'string', 'max:64'],
            'payment_id'         => ['required_without:merchant_reference', 'nullable', 'string', 'max:128'],
            'payload'            => ['nullable', 'array'],
        ]);

        $reference = (string) ($validated['merchant_reference'] ?? $validated['payment_id']);
        $callbackPayload = $validated['payload'] ?? $request->except(['gateway', 'merchant_reference', 'payment_id', 'payload']);

        $result = $this->paymentService->verifyPayment(
            referenceOrPaymentId: $reference,
            gateway: (string) $validated['gateway'],
            callbackPayload: $callbackPayload,
            actorType: 'customer'
        );

        return response()->json([
            'data' => [
                'transaction' => $this->formatTransaction($result['transaction']),
                'order'       => $this->orderService->formatDetailedOrder($result['order']),
            ],
            'idempotent_replay' => $result['idempotent_replay'],
        ]);
    }

    /**
     * POST|GET /api/payments/callback/{gateway}
     * Handles redirect/callback from payment gateway and performs server-side verification.
     */
    public function callback(Request $request, string $gateway): JsonResponse
    {
        $payload = $request->all();
        $reference = (string) (
            $payload['merchant_reference']
            ?? $payload['tran_id']
            ?? $payload['paymentID']
            ?? $payload['payment_ref_id']
            ?? $payload['order_id']
            ?? $payload['payment_id']
            ?? ''
        );

        $result = $this->paymentService->verifyPayment(
            referenceOrPaymentId: $reference,
            gateway: $gateway,
            callbackPayload: $payload,
            actorType: 'callback'
        );

        return response()->json([
            'data' => [
                'transaction' => $this->formatTransaction($result['transaction']),
                'order'       => $this->orderService->formatDetailedOrder($result['order']),
            ],
            'idempotent_replay' => $result['idempotent_replay'],
        ]);
    }

    /**
     * POST /api/webhooks/{gateway}
     * Processes asynchronous gateway webhook / IPN notifications idempotently.
     */
    public function webhook(Request $request, string $gateway): JsonResponse
    {
        // Gateway adapters expect Symfony's header shape: lower-cased name => list of values.
        $headers = $request->headers->all();

        $result = $this->paymentService->processWebhook(
            gateway: $gateway,
            payload: $request->all(),
            headers: $headers,
            rawBody: (string) $request->getContent()
        );

        return response()->json([
            'data' => [
                'webhook_event_id' => $result['webhook_event']->id,
                'gateway_event_id' => $result['webhook_event']->gateway_event_id,
                'event_type'       => $result['webhook_event']->event_type,
                'status'           => $result['webhook_event']->status,
                'transaction'      => $result['transaction'] ? $this->formatTransaction($result['transaction']) : null,
                'order'            => $result['order'] ? $this->orderService->formatDetailedOrder($result['order']) : null,
            ],
            'idempotent_replay' => $result['idempotent_replay'],
        ]);
    }

    private function formatTransaction(PaymentTransaction $tx): array
    {
        return [
            'id'                     => $tx->id,
            'order_id'               => $tx->order_id,
            'merchant_reference'     => $tx->merchant_reference,
            'gateway'                => $tx->gateway,
            'gateway_payment_id'     => $tx->gateway_payment_id,
            'gateway_transaction_id' => $tx->gateway_transaction_id,
            'gateway_payment_url'    => $tx->gateway_payment_url,
            'amount'                 => (int) $tx->amount,
            'refunded_amount'        => (int) $tx->refunded_amount,
            'currency'               => $tx->currency,
            'status'                 => $tx->status,
            'failure_reason'         => $tx->failure_reason,
            'metadata'               => $tx->metadata,
            'paid_at'                => $tx->paid_at?->toIso8601String(),
            'failed_at'              => $tx->failed_at?->toIso8601String(),
            'refunded_at'            => $tx->refunded_at?->toIso8601String(),
            'created_at'             => $tx->created_at?->toIso8601String(),
        ];
    }
}

