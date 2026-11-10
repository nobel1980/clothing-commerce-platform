<?php

namespace App\Modules\Orders\Controllers;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Payments\Models\PaymentRefund;
use App\Modules\Payments\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class AdminOrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
        protected PaymentService $paymentService,
    ) {}

    /**
     * Route-level `staff` only proves the caller is some kind of staff member. Each action
     * additionally requires the matching granular permission (view / manage / refund).
     */
    private function authorizePermission(Request $request, string $permission): void
    {
        $user = $request->user('sanctum') ?? $request->user();

        abort_unless(
            $user !== null && $user->can($permission),
            403,
            'You do not have permission to perform this action.'
        );
    }

    /**
     * GET /api/admin/orders
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'orders.view');

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $paginator = $this->orderService->adminList($request->only([
            'status',
            'payment_status',
            'fulfilment_status',
            'payment_method',
            'search',
            'from_date',
            'to_date',
        ]), $perPage);

        $items = collect($paginator->items())
            ->map(fn (Order $order) => $this->orderService->formatDetailedOrder($order))
            ->all();

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
     * GET /api/admin/orders/{order}
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        $this->authorizePermission($request, 'orders.view');

        return response()->json([
            'data' => $this->orderService->formatDetailedOrder($order),
        ]);
    }

    /**
     * POST /api/admin/orders/{order}/confirm
     */
    public function confirm(Request $request, Order $order): JsonResponse
    {
        $this->authorizePermission($request, 'orders.manage');

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $actorId = ($request->user('sanctum') ?? $request->user())?->id;

        $updated = $this->orderService->confirmOrder(
            orderOrId: $order,
            actorId: $actorId,
            notes: $validated['notes'] ?? null,
            actorType: 'staff'
        );

        return response()->json([
            'data' => $this->orderService->formatDetailedOrder($updated),
        ]);
    }

    /**
     * POST /api/admin/orders/{order}/process
     */
    public function process(Request $request, Order $order): JsonResponse
    {
        $this->authorizePermission($request, 'orders.manage');

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $actorId = ($request->user('sanctum') ?? $request->user())?->id;

        $updated = $this->orderService->processOrder(
            orderOrId: $order,
            actorId: $actorId,
            notes: $validated['notes'] ?? null
        );

        return response()->json([
            'data' => $this->orderService->formatDetailedOrder($updated),
        ]);
    }

    /**
     * POST /api/admin/orders/{order}/ship
     */
    public function ship(Request $request, Order $order): JsonResponse
    {
        $this->authorizePermission($request, 'orders.manage');

        $validated = $request->validate([
            'carrier'         => ['nullable', 'string', 'max:100'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'notes'           => ['nullable', 'string', 'max:500'],
        ]);

        $actorId = ($request->user('sanctum') ?? $request->user())?->id;

        $shipmentMetadata = array_filter([
            'carrier'         => $validated['carrier'] ?? null,
            'tracking_number' => $validated['tracking_number'] ?? null,
        ]);

        $updated = $this->orderService->shipOrder(
            orderOrId: $order,
            shipmentMetadata: $shipmentMetadata,
            actorId: $actorId,
            notes: $validated['notes'] ?? null
        );

        return response()->json([
            'data' => $this->orderService->formatDetailedOrder($updated),
        ]);
    }

    /**
     * POST /api/admin/orders/{order}/deliver
     */
    public function deliver(Request $request, Order $order): JsonResponse
    {
        $this->authorizePermission($request, 'orders.manage');

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $actorId = ($request->user('sanctum') ?? $request->user())?->id;

        $updated = $this->orderService->deliverOrder(
            orderOrId: $order,
            actorId: $actorId,
            notes: $validated['notes'] ?? null
        );

        return response()->json([
            'data' => $this->orderService->formatDetailedOrder($updated),
        ]);
    }

    /**
     * POST /api/admin/orders/{order}/cancel
     */
    public function cancel(Request $request, Order $order): JsonResponse
    {
        $this->authorizePermission($request, 'orders.manage');

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:191'],
            'notes'  => ['nullable', 'string', 'max:500'],
        ]);

        $actorId = ($request->user('sanctum') ?? $request->user())?->id;

        $updated = $this->orderService->cancelOrder(
            orderOrId: $order,
            reason: $validated['reason'],
            actorId: $actorId,
            actorType: 'staff',
            notes: $validated['notes'] ?? null
        );

        return response()->json([
            'data' => $this->orderService->formatDetailedOrder($updated),
        ]);
    }

    /**
     * POST /api/admin/orders/{order}/refund
     */
    public function refund(Request $request, Order $order): JsonResponse
    {
        // Refunds move money, so they need the stricter payments permission (not just orders.manage).
        $this->authorizePermission($request, 'payments.manage');

        $validated = $request->validate([
            'amount'          => ['required', 'integer', 'min:1'],
            'reason'          => ['required', 'string', 'max:191'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ]);

        $actorId = ($request->user('sanctum') ?? $request->user())?->id;
        $idempotencyKey = $request->header('Idempotency-Key')
            ?? $validated['idempotency_key']
            ?? ('refund-' . $order->order_number . '-' . Str::uuid()->toString());

        /** @var PaymentRefund $refund */
        $refund = $this->paymentService->refundOrder(
            orderOrId: $order,
            refundAmountPaisa: (int) $validated['amount'],
            reason: $validated['reason'],
            idempotencyKey: (string) $idempotencyKey,
            actorId: $actorId
        );

        return response()->json([
            'data' => [
                'refund' => [
                    'id'                => $refund->id,
                    'gateway'           => $refund->gateway,
                    'gateway_refund_id' => $refund->gateway_refund_id,
                    'amount'            => (int) $refund->amount,
                    'currency'          => $refund->currency,
                    'status'            => $refund->status,
                    'reason'            => $refund->reason,
                    'refunded_at'       => $refund->refunded_at?->toIso8601String(),
                ],
                'order' => $this->orderService->formatDetailedOrder($order->fresh() ?? $order),
            ],
        ]);
    }
}

