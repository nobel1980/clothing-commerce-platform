<?php

namespace App\Modules\Orders\Services;

use App\Modules\Checkout\Services\CheckoutService;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderStatusHistory;
use App\Modules\Payments\Models\PaymentTransaction;
use App\Shared\Contracts\Inventory\InventoryServiceContract;
use App\Shared\Events\Orders\OrderCancelled;
use App\Shared\Events\Orders\OrderConfirmed;
use App\Shared\Events\Orders\OrderDelivered;
use App\Shared\Events\Orders\OrderShipped;
use App\Shared\Exceptions\Orders\InvalidOrderStateException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public const CUSTOMER_CANCELLABLE_STATUSES = [
        'pending',
        'pending_confirmation',
        'pending_payment',
        'awaiting_payment',
        'payment_failed',
        'confirmed',
    ];

    public const ADMIN_CANCELLABLE_STATUSES = [
        'pending',
        'pending_confirmation',
        'pending_payment',
        'awaiting_payment',
        'payment_failed',
        'confirmed',
        'processing',
    ];

    public function __construct(
        protected InventoryServiceContract $inventoryService
    ) {}

    /**
     * Record an immutable OrderStatusHistory entry.
     */
    public function recordStatusHistory(
        Order $order,
        ?string $fromStatus,
        string $toStatus,
        ?string $fromPaymentStatus = null,
        ?string $toPaymentStatus = null,
        ?string $fromFulfilmentStatus = null,
        ?string $toFulfilmentStatus = null,
        string $actorType = 'system',
        ?int $actorId = null,
        ?string $reason = null,
        ?string $notes = null,
        ?array $metadata = null
    ): OrderStatusHistory {
        return OrderStatusHistory::query()->create([
            'order_id'               => $order->id,
            'from_status'            => $fromStatus,
            'to_status'              => $toStatus,
            'from_payment_status'    => $fromPaymentStatus,
            'to_payment_status'      => $toPaymentStatus ?? $order->payment_status,
            'from_fulfilment_status' => $fromFulfilmentStatus,
            'to_fulfilment_status'   => $toFulfilmentStatus ?? ($order->fulfilment_status ?? 'unfulfilled'),
            'actor_type'             => $actorType,
            'actor_id'               => $actorId,
            'reason'                 => $reason,
            'notes'                  => $notes,
            'metadata'               => $metadata,
            'created_at'             => now(),
        ]);
    }

    /**
     * Paginated list of orders for Admin backoffice.
     */
    public function adminList(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Order::query()
            ->with(['items', 'statusHistories', 'paymentTransactions']);

        if (!empty($filters['status'])) {
            $query->where('status', strtolower((string) $filters['status']));
        }

        if (!empty($filters['payment_status'])) {
            $query->where('payment_status', strtolower((string) $filters['payment_status']));
        }

        if (!empty($filters['fulfilment_status'])) {
            $query->where('fulfilment_status', strtolower((string) $filters['fulfilment_status']));
        }

        if (!empty($filters['payment_method'])) {
            $query->where('payment_method', strtolower((string) $filters['payment_method']));
        }

        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where('order_number', 'like', "%{$search}%");
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('placed_at', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('placed_at', '<=', $filters['to_date']);
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    /**
     * Confirm an order (e.g. COD admin confirmation or post-payment confirmation).
     */
    public function confirmOrder(
        int|Order $orderOrId,
        ?int $actorId = null,
        ?string $notes = null,
        string $actorType = 'staff'
    ): Order {
        $event = null;

        $updatedOrder = DB::transaction(function () use ($orderOrId, $actorId, $notes, $actorType, &$event) {
            $order = $this->lockOrder($orderOrId);

            if ($order->status === 'confirmed') {
                return $order->load(['items', 'statusHistories', 'paymentTransactions']);
            }

            if (!in_array($order->status, ['pending', 'pending_confirmation', 'pending_payment', 'awaiting_payment'], true)) {
                throw new InvalidOrderStateException(
                    "Order '{$order->order_number}' in status '{$order->status}' cannot be transitioned to 'confirmed'.",
                    ['current_status' => $order->status]
                );
            }

            $fromStatus = $order->status;
            $order->update([
                'status'       => 'confirmed',
                'confirmed_at' => now(),
            ]);

            $this->recordStatusHistory(
                order: $order,
                fromStatus: $fromStatus,
                toStatus: 'confirmed',
                fromPaymentStatus: $order->payment_status,
                toPaymentStatus: $order->payment_status,
                fromFulfilmentStatus: $order->fulfilment_status,
                toFulfilmentStatus: $order->fulfilment_status,
                actorType: $actorType,
                actorId: $actorId,
                reason: 'Order confirmed',
                notes: $notes
            );

            $event = new OrderConfirmed($order->id, $order->order_number, $actorId);

            return $order->fresh(['items', 'statusHistories', 'paymentTransactions']) ?? $order;
        });

        if ($event !== null) {
            event($event);
        }

        return $updatedOrder;
    }

    /**
     * Transition a confirmed order to 'processing'.
     */
    public function processOrder(
        int|Order $orderOrId,
        ?int $actorId = null,
        ?string $notes = null
    ): Order {
        return DB::transaction(function () use ($orderOrId, $actorId, $notes) {
            $order = $this->lockOrder($orderOrId);

            if ($order->status === 'processing') {
                return $order->load(['items', 'statusHistories', 'paymentTransactions']);
            }

            if (!in_array($order->status, ['confirmed'], true)) {
                throw new InvalidOrderStateException(
                    "Order '{$order->order_number}' must be 'confirmed' before moving to 'processing' (current: '{$order->status}').",
                    ['current_status' => $order->status]
                );
            }

            $fromStatus = $order->status;
            $order->update([
                'status' => 'processing',
            ]);

            $this->recordStatusHistory(
                order: $order,
                fromStatus: $fromStatus,
                toStatus: 'processing',
                fromPaymentStatus: $order->payment_status,
                toPaymentStatus: $order->payment_status,
                fromFulfilmentStatus: $order->fulfilment_status,
                toFulfilmentStatus: $order->fulfilment_status,
                actorType: 'staff',
                actorId: $actorId,
                reason: 'Fulfilment started',
                notes: $notes
            );

            return $order->fresh(['items', 'statusHistories', 'paymentTransactions']) ?? $order;
        });
    }

    /**
     * Ship an order and commit its inventory stock reservation (`quantity_on_hand` & `quantity_reserved` deducted).
     */
    public function shipOrder(
        int|Order $orderOrId,
        array $shipmentMetadata = [],
        ?int $actorId = null,
        ?string $notes = null
    ): Order {
        $event = null;

        $updatedOrder = DB::transaction(function () use ($orderOrId, $shipmentMetadata, $actorId, $notes, &$event) {
            $order = $this->lockOrder($orderOrId);

            if ($order->status === 'shipped') {
                return $order->load(['items', 'statusHistories', 'paymentTransactions']);
            }

            if (!in_array($order->status, ['confirmed', 'processing'], true)) {
                throw new InvalidOrderStateException(
                    "Order '{$order->order_number}' in status '{$order->status}' cannot be shipped.",
                    ['current_status' => $order->status]
                );
            }

            // Commit stock reservation so quantity_on_hand and quantity_reserved are permanently deducted
            if ($order->stock_reservation_id !== null) {
                $reservation = $this->inventoryService->getReservation($order->stock_reservation_id);
                if ($reservation !== null && $reservation->status === 'active') {
                    $this->inventoryService->commitReservation($order->stock_reservation_id, $actorId);
                }
            }

            $fromStatus = $order->status;
            $fromFulfilment = $order->fulfilment_status ?? 'unfulfilled';

            $order->update([
                'status'            => 'shipped',
                'fulfilment_status' => 'fulfilled',
                'shipped_at'        => now(),
            ]);

            $this->recordStatusHistory(
                order: $order,
                fromStatus: $fromStatus,
                toStatus: 'shipped',
                fromPaymentStatus: $order->payment_status,
                toPaymentStatus: $order->payment_status,
                fromFulfilmentStatus: $fromFulfilment,
                toFulfilmentStatus: 'fulfilled',
                actorType: 'staff',
                actorId: $actorId,
                reason: 'Order shipped',
                notes: $notes,
                metadata: !empty($shipmentMetadata) ? $shipmentMetadata : null
            );

            $event = new OrderShipped($order->id, $order->order_number, $actorId);

            return $order->fresh(['items', 'statusHistories', 'paymentTransactions']) ?? $order;
        });

        if ($event !== null) {
            event($event);
        }

        return $updatedOrder;
    }

    /**
     * Mark a shipped order as delivered (and mark COD payment as paid upon collection).
     */
    public function deliverOrder(
        int|Order $orderOrId,
        ?int $actorId = null,
        ?string $notes = null
    ): Order {
        $event = null;

        $updatedOrder = DB::transaction(function () use ($orderOrId, $actorId, $notes, &$event) {
            $order = $this->lockOrder($orderOrId);

            if ($order->status === 'delivered') {
                return $order->load(['items', 'statusHistories', 'paymentTransactions']);
            }

            if ($order->status !== 'shipped') {
                throw new InvalidOrderStateException(
                    "Order '{$order->order_number}' must be in 'shipped' status before marking as 'delivered' (current: '{$order->status}').",
                    ['current_status' => $order->status]
                );
            }

            $fromStatus = $order->status;
            $fromPaymentStatus = $order->payment_status;
            $fromFulfilmentStatus = $order->fulfilment_status ?? 'fulfilled';

            $newPaymentStatus = $order->payment_status;
            if ($order->payment_method === 'cod' && in_array($order->payment_status, ['pending', 'cod_pending'], true)) {
                $newPaymentStatus = 'paid';

                PaymentTransaction::query()
                    ->where('order_id', $order->id)
                    ->where('gateway', 'cod')
                    ->update([
                        'status'  => 'paid',
                        'paid_at' => now(),
                    ]);
            }

            $order->update([
                'status'            => 'delivered',
                'payment_status'    => $newPaymentStatus,
                'fulfilment_status' => 'delivered',
                'delivered_at'      => now(),
            ]);

            $this->recordStatusHistory(
                order: $order,
                fromStatus: $fromStatus,
                toStatus: 'delivered',
                fromPaymentStatus: $fromPaymentStatus,
                toPaymentStatus: $newPaymentStatus,
                fromFulfilmentStatus: $fromFulfilmentStatus,
                toFulfilmentStatus: 'delivered',
                actorType: 'staff',
                actorId: $actorId,
                reason: 'Order delivered to customer',
                notes: $notes
            );

            $event = new OrderDelivered($order->id, $order->order_number, $actorId);

            return $order->fresh(['items', 'statusHistories', 'paymentTransactions']) ?? $order;
        });

        if ($event !== null) {
            event($event);
        }

        return $updatedOrder;
    }

    /**
     * Cancel an order and release its reserved inventory stock idempotently.
     */
    public function cancelOrder(
        int|Order $orderOrId,
        string $reason,
        ?int $actorId = null,
        string $actorType = 'customer',
        ?string $notes = null
    ): Order {
        $event = null;

        $updatedOrder = DB::transaction(function () use ($orderOrId, $reason, $actorId, $actorType, $notes, &$event) {
            $order = $this->lockOrder($orderOrId);

            if ($order->status === 'cancelled') {
                return $order->load(['items', 'statusHistories', 'paymentTransactions']);
            }

            $allowedStatuses = $actorType === 'customer'
                ? self::CUSTOMER_CANCELLABLE_STATUSES
                : self::ADMIN_CANCELLABLE_STATUSES;

            if (!in_array($order->status, $allowedStatuses, true)) {
                throw new InvalidOrderStateException(
                    "Order '{$order->order_number}' in status '{$order->status}' cannot be cancelled by {$actorType}.",
                    [
                        'current_status' => $order->status,
                        'actor_type'     => $actorType,
                    ]
                );
            }

            // Release active stock reservation back to available inventory
            if ($order->stock_reservation_id !== null) {
                $reservation = $this->inventoryService->getReservation($order->stock_reservation_id);
                if ($reservation !== null && $reservation->status === 'active') {
                    $this->inventoryService->releaseReservation(
                        reservationIdOrKey: $order->stock_reservation_id,
                        reason: "Order {$order->order_number} cancelled: {$reason}",
                        performedBy: $actorId
                    );
                }
            }

            $fromStatus = $order->status;
            $order->update([
                'status'              => 'cancelled',
                'cancelled_at'        => now(),
                'cancellation_reason' => substr(trim($reason), 0, 191),
            ]);

            $this->recordStatusHistory(
                order: $order,
                fromStatus: $fromStatus,
                toStatus: 'cancelled',
                fromPaymentStatus: $order->payment_status,
                toPaymentStatus: $order->payment_status,
                fromFulfilmentStatus: $order->fulfilment_status,
                toFulfilmentStatus: $order->fulfilment_status,
                actorType: $actorType,
                actorId: $actorId,
                reason: $reason,
                notes: $notes
            );

            $event = new OrderCancelled($order->id, $order->order_number, $reason, $actorId);

            return $order->fresh(['items', 'statusHistories', 'paymentTransactions']) ?? $order;
        });

        if ($event !== null) {
            event($event);
        }

        return $updatedOrder;
    }

    /**
     * Transition a shipped or delivered order to 'returned' and record status history.
     */
    public function markOrderReturned(
        int|Order $orderOrId,
        string $reason = 'Order returned',
        ?int $actorId = null,
        string $actorType = 'system',
        ?string $notes = null
    ): Order {
        return DB::transaction(function () use ($orderOrId, $reason, $actorId, $actorType, $notes) {
            $order = $this->lockOrder($orderOrId);

            if ($order->status === 'returned') {
                return $order->load(['items', 'statusHistories', 'paymentTransactions']);
            }

            if (!in_array($order->status, ['shipped', 'delivered', 'processing', 'confirmed'], true)) {
                throw new InvalidOrderStateException(
                    "Order '{$order->order_number}' in status '{$order->status}' cannot be marked as returned.",
                    ['current_status' => $order->status]
                );
            }

            $fromStatus = $order->status;
            $fromFulfilment = $order->fulfilment_status ?? 'fulfilled';

            $order->update([
                'status'            => 'returned',
                'fulfilment_status' => 'returned',
            ]);

            $this->recordStatusHistory(
                order: $order,
                fromStatus: $fromStatus,
                toStatus: 'returned',
                fromPaymentStatus: $order->payment_status,
                toPaymentStatus: $order->payment_status,
                fromFulfilmentStatus: $fromFulfilment,
                toFulfilmentStatus: 'returned',
                actorType: $actorType,
                actorId: $actorId,
                reason: $reason,
                notes: $notes
            );

            return $order->fresh(['items', 'statusHistories', 'paymentTransactions']) ?? $order;
        });
    }

    /**
     * Format an Order with its items, status histories, and redacted payment transactions.
     */
    public function formatDetailedOrder(Order $order): array
    {
        $order->loadMissing(['items', 'statusHistories', 'paymentTransactions', 'refunds']);
        /** @var CheckoutService $checkoutService */
        $checkoutService = app(CheckoutService::class);
        $base = $checkoutService->formatOrder($order);

        $base['fulfilment_status'] = $order->fulfilment_status ?? 'unfulfilled';
        $base['confirmed_at']      = $order->confirmed_at?->toIso8601String();
        $base['shipped_at']        = $order->shipped_at?->toIso8601String();
        $base['delivered_at']      = $order->delivered_at?->toIso8601String();
        $base['cancelled_at']      = $order->cancelled_at?->toIso8601String();
        $base['cancellation_reason'] = $order->cancellation_reason;

        $base['status_histories'] = $order->statusHistories->map(fn (OrderStatusHistory $h) => [
            'id'                     => $h->id,
            'from_status'            => $h->from_status,
            'to_status'              => $h->to_status,
            'from_payment_status'    => $h->from_payment_status,
            'to_payment_status'      => $h->to_payment_status,
            'from_fulfilment_status' => $h->from_fulfilment_status,
            'to_fulfilment_status'   => $h->to_fulfilment_status,
            'actor_type'             => $h->actor_type,
            'actor_id'               => $h->actor_id,
            'reason'                 => $h->reason,
            'notes'                  => $h->notes,
            'metadata'               => $h->metadata,
            'created_at'             => $h->created_at?->toIso8601String(),
        ])->all();

        $base['payment_transactions'] = $order->paymentTransactions->map(fn (PaymentTransaction $tx) => [
            'id'                     => $tx->id,
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
            'paid_at'                => $tx->paid_at?->toIso8601String(),
            'failed_at'              => $tx->failed_at?->toIso8601String(),
            'refunded_at'            => $tx->refunded_at?->toIso8601String(),
            'created_at'             => $tx->created_at?->toIso8601String(),
        ])->all();

        return $base;
    }

    private function lockOrder(int|Order $orderOrId): Order
    {
        $id = $orderOrId instanceof Order ? $orderOrId->id : (int) $orderOrId;

        /** @var Order|null $order */
        $order = Order::query()
            ->where('id', $id)
            ->lockForUpdate()
            ->first();

        if (!$order) {
            abort(404, 'Order not found.');
        }

        return $order;
    }
}

