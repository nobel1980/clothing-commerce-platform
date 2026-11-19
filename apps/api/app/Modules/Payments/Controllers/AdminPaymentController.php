<?php

namespace App\Modules\Payments\Controllers;

use App\Modules\Identity\Services\AuditService;
use App\Modules\Payments\Models\PaymentGatewayLog;
use App\Modules\Payments\Models\PaymentRefund;
use App\Modules\Payments\Models\PaymentTransaction;
use App\Modules\Payments\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class AdminPaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService,
        protected AuditService $auditService,
    ) {}

    private function authorizePermission(Request $request, string $permission): void
    {
        $user = $request->user('sanctum') ?? $request->user();

        abort_unless(
            $user !== null && ($user->can($permission) || $user->isSuperAdmin()),
            403,
            'You do not have permission to perform this payment action.'
        );
    }

    /**
     * GET /api/admin/payments
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'payments.view');

        $query = PaymentTransaction::query()
            ->with(['order:id,order_number,status,payment_status,total_amount,user_id', 'refunds'])
            ->orderByDesc('id');

        if ($request->filled('gateway')) {
            $query->where('gateway', (string) $request->query('gateway'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('merchant_reference', 'like', "%{$search}%")
                  ->orWhere('gateway_payment_id', 'like', "%{$search}%")
                  ->orWhere('gateway_transaction_id', 'like', "%{$search}%")
                  ->orWhereHas('order', fn ($oq) => $oq->where('order_number', 'like', "%{$search}%"));
            });
        }

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $paginator = $query->paginate($perPage);

        $items = collect($paginator->items())
            ->map(fn (PaymentTransaction $tx) => $this->formatTransaction($tx))
            ->all();

        $summary = [
            'total_paid_amount'     => (int) PaymentTransaction::query()->whereIn('status', ['paid', 'partially_refunded'])->sum('amount'),
            'total_refunded_amount' => (int) PaymentTransaction::query()->sum('refunded_amount'),
            'paid_count'            => PaymentTransaction::query()->where('status', 'paid')->count(),
            'pending_count'         => PaymentTransaction::query()->whereIn('status', ['initiated', 'pending', 'pending_verification', 'cod_pending'])->count(),
            'failed_count'          => PaymentTransaction::query()->where('status', 'failed')->count(),
        ];

        return response()->json([
            'data'    => $items,
            'summary' => $summary,
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    /**
     * GET /api/admin/payments/refunds
     */
    public function refunds(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'payments.view');

        $query = PaymentRefund::query()
            ->with(['order:id,order_number', 'transaction:id,merchant_reference,gateway'])
            ->orderByDesc('id');

        if ($request->filled('gateway')) {
            $query->where('gateway', (string) $request->query('gateway'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $paginator = $query->paginate($perPage);

        $items = collect($paginator->items())->map(fn (PaymentRefund $r) => [
            'id'                     => $r->id,
            'payment_transaction_id' => $r->payment_transaction_id,
            'order_id'               => $r->order_id,
            'order_number'           => $r->order?->order_number,
            'merchant_reference'     => $r->transaction?->merchant_reference,
            'gateway'                => $r->gateway,
            'gateway_refund_id'      => $r->gateway_refund_id,
            'amount'                 => (int) $r->amount,
            'currency'               => $r->currency,
            'status'                 => $r->status,
            'reason'                 => $r->reason,
            'initiated_by'           => $r->initiated_by,
            'refunded_at'            => $r->refunded_at?->toIso8601String(),
            'created_at'             => $r->created_at?->toIso8601String(),
        ])->all();

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    /**
     * GET /api/admin/payments/logs
     */
    public function logs(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'payments.view');

        $query = PaymentGatewayLog::query()->orderByDesc('id');

        if ($request->filled('gateway')) {
            $query->where('gateway', (string) $request->query('gateway'));
        }

        if ($request->filled('action')) {
            $query->where('action', (string) $request->query('action'));
        }

        $perPage = min(max((int) $request->query('per_page', 25), 1), 100);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    /**
     * GET /api/admin/payments/{transaction}
     */
    public function show(Request $request, PaymentTransaction $transaction): JsonResponse
    {
        $this->authorizePermission($request, 'payments.view');

        $transaction->load(['order:id,order_number,status,payment_status,total_amount', 'refunds']);

        $logs = PaymentGatewayLog::query()
            ->where('payment_transaction_id', $transaction->id)
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        $payload = $this->formatTransaction($transaction);
        $payload['gateway_logs'] = $logs->all();

        return response()->json([
            'data' => $payload,
        ]);
    }

    /**
     * POST /api/admin/payments/{transaction}/refund
     */
    public function refund(Request $request, PaymentTransaction $transaction): JsonResponse
    {
        $this->authorizePermission($request, 'payments.manage');

        $validated = $request->validate([
            'amount'          => ['required', 'integer', 'min:1'],
            'reason'          => ['required', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ]);

        $actor = $request->user('sanctum') ?? $request->user();
        $idempotencyKey = $validated['idempotency_key'] ?? ('admin-tx-refund-' . $transaction->id . '-' . Str::uuid());

        $refund = $this->paymentService->initiateRefund(
            orderOrId: (int) $transaction->order_id,
            amountPaisa: (int) $validated['amount'],
            reason: $validated['reason'],
            idempotencyKey: $idempotencyKey,
            actorId: $actor?->id,
            transactionId: $transaction->id
        );

        $this->auditService->log('payment.refund_initiated', $actor, $transaction, null, [
            'order_id'  => $transaction->order_id,
            'refund_id' => $refund->id,
            'amount'    => $refund->amount,
            'status'    => $refund->status,
            'reason'    => $refund->reason,
        ]);

        return response()->json([
            'message' => 'Refund processed.',
            'data'    => [
                'refund_id'         => $refund->id,
                'status'            => $refund->status,
                'amount'            => (int) $refund->amount,
                'currency'          => $refund->currency,
                'gateway_refund_id' => $refund->gateway_refund_id,
                'reason'            => $refund->reason,
                'refunded_at'       => $refund->refunded_at?->toIso8601String(),
                'transaction'       => $this->formatTransaction($transaction->fresh(['order', 'refunds']) ?? $transaction),
            ],
        ]);
    }

    private function formatTransaction(PaymentTransaction $tx): array
    {
        return [
            'id'                     => $tx->id,
            'order_id'               => $tx->order_id,
            'order_number'           => $tx->order?->order_number,
            'order_status'           => $tx->order?->status,
            'merchant_reference'     => $tx->merchant_reference,
            'gateway'                => $tx->gateway,
            'gateway_payment_id'     => $tx->gateway_payment_id,
            'gateway_transaction_id' => $tx->gateway_transaction_id,
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
            'refunds'                => $tx->relationLoaded('refunds')
                ? $tx->refunds->map(fn (PaymentRefund $r) => [
                    'id'                => $r->id,
                    'amount'            => (int) $r->amount,
                    'status'            => $r->status,
                    'reason'            => $r->reason,
                    'gateway_refund_id' => $r->gateway_refund_id,
                    'refunded_at'       => $r->refunded_at?->toIso8601String(),
                ])->all()
                : [],
        ];
    }
}

