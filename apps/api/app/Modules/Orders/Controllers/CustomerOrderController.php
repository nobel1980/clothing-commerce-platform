<?php

namespace App\Modules\Orders\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Policies\OrderPolicy;
use App\Modules\Orders\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CustomerOrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
        protected OrderPolicy $orderPolicy,
    ) {}

    /**
     * GET /api/orders
     * GET /api/account/orders
     * List the authenticated customer's order history.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user('sanctum') ?? $request->user();
        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $orders = Order::query()
            ->with(['items', 'statusHistories', 'paymentTransactions'])
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (Order $order) => $this->orderService->formatDetailedOrder($order))
            ->all();

        return response()->json([
            'data' => $orders,
        ]);
    }

    /**
     * GET /api/orders/{orderNumber}
     * Show detailed order information including item snapshots, status history, and payment records.
     */
    public function show(Request $request, string $orderNumber): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user('sanctum') ?? $request->user();

        $order = Order::query()
            ->with(['items', 'statusHistories', 'paymentTransactions', 'refunds'])
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        if (!$this->orderPolicy->view($user, $order)) {
            return response()->json([
                'message' => 'You are not authorized to view this order.',
            ], 403);
        }

        return response()->json([
            'data' => $this->orderService->formatDetailedOrder($order),
        ]);
    }

    /**
     * POST /api/orders/{orderNumber}/cancel
     * Cancel an order before shipment and release reserved inventory stock.
     */
    public function cancel(Request $request, string $orderNumber): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user('sanctum') ?? $request->user();

        $order = Order::query()
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        if (!$this->orderPolicy->cancel($user, $order)) {
            return response()->json([
                'message' => 'You are not authorized to cancel this order.',
            ], 403);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:191'],
            'notes'  => ['nullable', 'string', 'max:500'],
        ]);

        $updatedOrder = $this->orderService->cancelOrder(
            orderOrId: $order,
            reason: $validated['reason'],
            actorId: $user?->id,
            actorType: $user?->isStaff() ? 'staff' : 'customer',
            notes: $validated['notes'] ?? null
        );

        return response()->json([
            'data' => $this->orderService->formatDetailedOrder($updatedOrder),
        ]);
    }
}

