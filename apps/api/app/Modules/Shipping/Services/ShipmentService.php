<?php

namespace App\Modules\Shipping\Services;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Payments\Models\PaymentTransaction;
use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Models\ShipmentTrackingEvent;
use App\Shared\Contracts\Inventory\InventoryServiceContract;
use App\Shared\Contracts\Inventory\StockAdjustmentData;
use App\Shared\Contracts\Shipping\CreateShipmentRequest;
use App\Shared\Contracts\Shipping\DeliveryChargeResult;
use App\Shared\Contracts\Shipping\DeliveryQuoteRequest;
use App\Shared\Events\Shipping\CodCollectionReconciled;
use App\Shared\Events\Shipping\ParcelReturned;
use App\Shared\Events\Shipping\ShipmentCreated;
use App\Shared\Events\Shipping\ShipmentDelivered;
use App\Shared\Exceptions\Shipping\CourierApiException;
use App\Shared\Exceptions\Shipping\DuplicateShipmentException;
use App\Shared\Exceptions\Shipping\InvalidShipmentStateException;
use App\Shared\Exceptions\Shipping\ShippingException;
use App\Shared\Money\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class ShipmentService
{
    public const CANCELLABLE_STATUSES = [
        'pending',
        'created',
    ];

    public function __construct(
        protected CourierProviderFactory $providerFactory,
        protected OrderService $orderService,
        protected InventoryServiceContract $inventoryService,
        protected CourierAuditLogger $auditLogger,
    ) {}

    /**
     * Calculate delivery charge via the specified courier provider.
     */
    public function calculateDeliveryCharge(
        DeliveryQuoteRequest $request,
        ?string $courier = null
    ): DeliveryChargeResult {
        $provider = $this->providerFactory->resolve($courier);
        $result = $provider->calculateDeliveryCharge($request);

        if (!$result->successful) {
            throw new CourierApiException(
                $result->errorMessage ?? "Failed to calculate delivery charge via '{$provider->providerName()}'.",
                ['courier' => $provider->providerName()]
            );
        }

        return $result;
    }

    /**
     * Create a courier shipment/consignment for an order.
     * Prevents duplicate shipments and never holds DB locks during external courier HTTP calls.
     *
     * @return array{shipment: Shipment, idempotent_replay: bool}
     */
    public function createShipment(
        int|Order $orderOrId,
        string $courier,
        string $idempotencyKey,
        array $options = [],
        ?int $actorId = null
    ): array {
        $normalizedKey = trim($idempotencyKey);
        if ($normalizedKey === '') {
            throw new ShippingException('An idempotency_key is required to create a shipment.', 422, 'idempotency_key');
        }

        // 1. Fast-path idempotency check (if already succeeded, return replay)
        $existingByKey = Shipment::query()
            ->with(['trackingEvents', 'order'])
            ->where('idempotency_key', $normalizedKey)
            ->first();

        if ($existingByKey !== null && $existingByKey->consignment_id !== null && $existingByKey->status !== 'failed') {
            return [
                'shipment'          => $existingByKey,
                'idempotent_replay' => true,
            ];
        }

        $provider = $this->providerFactory->resolve($courier);
        $courierName = $provider->providerName();
        $idempotentReplay = false;

        // 2. Lock Order & reserve/reuse a pending Shipment row inside a quick local DB transaction
        /** @var array{shipment: Shipment, order: Order} $prepared */
        $prepared = DB::transaction(function () use (
            $orderOrId,
            $courierName,
            $normalizedKey,
            $options,
            $actorId,
            &$idempotentReplay
        ) {
            $lockedByKey = Shipment::query()
                ->with(['trackingEvents', 'order'])
                ->where('idempotency_key', $normalizedKey)
                ->lockForUpdate()
                ->first();

            if ($lockedByKey !== null && $lockedByKey->consignment_id !== null && $lockedByKey->status !== 'failed') {
                $idempotentReplay = true;
                return [
                    'shipment' => $lockedByKey,
                    'order'    => $lockedByKey->order,
                ];
            }

            $orderId = $orderOrId instanceof Order ? $orderOrId->id : (int) $orderOrId;
            /** @var Order|null $order */
            $order = Order::query()
                ->with('items')
                ->where('id', $orderId)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                abort(404, 'Order not found.');
            }

            if (!in_array($order->status, ['confirmed', 'processing', 'shipped'], true)) {
                throw new InvalidShipmentStateException(
                    "Order '{$order->order_number}' in status '{$order->status}' cannot be dispatched to a courier.",
                    ['order_status' => $order->status]
                );
            }

            // Prevent duplicate active shipments for the same order
            $activeShipment = Shipment::query()
                ->where('order_id', $order->id)
                ->whereNotIn('status', ['cancelled', 'failed'])
                ->when($lockedByKey !== null, fn ($q) => $q->where('id', '!=', $lockedByKey->id))
                ->lockForUpdate()
                ->first();

            if ($activeShipment !== null) {
                throw new DuplicateShipmentException(
                    "Order '{$order->order_number}' already has an active shipment ('{$activeShipment->consignment_id}' via {$activeShipment->courier}).",
                    [
                        'order_number'   => $order->order_number,
                        'shipment_id'    => $activeShipment->id,
                        'consignment_id' => $activeShipment->consignment_id,
                        'courier'        => $activeShipment->courier,
                    ]
                );
            }

            $addr = $order->shipping_address_snapshot ?? [];
            $isCod = $order->payment_method === 'cod' && $order->payment_status !== 'paid';
            $codAmountPaisa = $isCod ? (int) $order->total_amount : 0;
            $totalQty = max(1, (int) $order->items->sum('quantity'));
            $weightGrams = isset($options['weight_grams'])
                ? max(100, (int) $options['weight_grams'])
                : max(500, $totalQty * 400);

            if ($lockedByKey !== null) {
                // Retry a previously failed shipment attempt with the same idempotency_key
                $lockedByKey->update([
                    'courier'        => $courierName,
                    'status'         => 'pending',
                    'weight_grams'   => $weightGrams,
                    'failure_reason' => null,
                    'failed_at'      => null,
                ]);

                return [
                    'shipment' => $lockedByKey->fresh() ?? $lockedByKey,
                    'order'    => $order,
                ];
            }

            $shipment = Shipment::query()->create([
                'order_id'                  => $order->id,
                'idempotency_key'           => $normalizedKey,
                'courier'                   => $courierName,
                'merchant_order_reference'  => $order->order_number,
                'status'                    => 'pending',
                'shipping_charge'           => (int) $order->shipping_amount,
                'cod_amount'                => $codAmountPaisa,
                'cod_collected_amount'      => 0,
                'cod_reconciliation_status' => $codAmountPaisa > 0 ? 'pending' : 'not_applicable',
                'currency'                  => $order->currency ?? 'BDT',
                'weight_grams'              => $weightGrams,
                'item_quantity'             => $totalQty,
                'recipient_snapshot'        => $addr,
                'created_by'                => $actorId,
            ]);

            return [
                'shipment' => $shipment,
                'order'    => $order,
            ];
        });

        if ($idempotentReplay) {
            return [
                'shipment'          => $prepared['shipment'],
                'idempotent_replay' => true,
            ];
        }

        $shipment = $prepared['shipment'];
        $order = $prepared['order'];
        $addr = $order->shipping_address_snapshot ?? [];

        // 3. Call CourierProvider::createShipment OUTSIDE database transaction (zero DB locks held!)
        $createRequest = new CreateShipmentRequest(
            orderId: $order->id,
            orderNumber: $order->order_number,
            merchantOrderReference: $shipment->merchant_order_reference,
            recipientName: (string) ($addr['name'] ?? 'Customer'),
            recipientPhone: (string) ($addr['phone'] ?? '01700000000'),
            recipientAddress: (string) ($addr['street_address'] ?? 'Dhaka'),
            recipientDivision: (string) ($addr['division'] ?? 'Dhaka'),
            recipientDistrict: (string) ($addr['district'] ?? 'Dhaka'),
            recipientUpazila: isset($addr['upazila_thana']) ? (string) $addr['upazila_thana'] : null,
            recipientPostalCode: isset($addr['postal_code']) ? (string) $addr['postal_code'] : null,
            codAmountPaisa: (int) $shipment->cod_amount,
            declaredValuePaisa: (int) $order->total_amount,
            weightGrams: (int) $shipment->weight_grams,
            itemQuantity: (int) $shipment->item_quantity,
            itemDescription: (string) ($options['item_description'] ?? "Order {$order->order_number}"),
            specialInstruction: isset($options['special_instruction'])
                ? (string) $options['special_instruction']
                : ($addr['delivery_instructions'] ?? $order->notes ?? null),
            deliveryType: $order->shipping_method === 'express' ? 'express' : 'standard',
            recipientCityId: isset($options['recipient_city_id']) ? (int) $options['recipient_city_id'] : null,
            recipientZoneId: isset($options['recipient_zone_id']) ? (int) $options['recipient_zone_id'] : null,
            recipientAreaId: isset($options['recipient_area_id']) ? (int) $options['recipient_area_id'] : null
        );

        $result = $provider->createShipment($createRequest);

        if (!$result->successful || $result->consignmentId === null || $result->consignmentId === '') {
            $shipment->update([
                'status'           => 'failed',
                'failure_reason'   => substr((string) ($result->errorMessage ?? 'Courier API rejected shipment.'), 0, 191),
                'failed_at'        => now(),
                'courier_metadata' => [
                    'raw_request'  => $result->rawRequest,
                    'raw_response' => $result->rawResponse,
                ],
            ]);

            throw new CourierApiException(
                $result->errorMessage ?? "Failed to create shipment with courier '{$courierName}'.",
                [
                    'courier'     => $courierName,
                    'shipment_id' => $shipment->id,
                ]
            );
        }

        // 4. Persist consignment details, record initial tracking event, and transition Order to 'shipped'
        $updatedShipment = DB::transaction(function () use ($shipment, $order, $result, $actorId) {
            $shippingChargePaisa = $result->shippingChargePaisa > 0
                ? $result->shippingChargePaisa
                : (int) $shipment->shipping_charge;

            $shipment->update([
                'consignment_id'   => $result->consignmentId,
                'tracking_number'  => $result->trackingNumber ?? $result->consignmentId,
                'tracking_url'     => $result->trackingUrl,
                'status'           => $result->internalStatus,
                'shipping_charge'  => $shippingChargePaisa,
                'failure_reason'   => null,
                'failed_at'        => null,
                'courier_metadata' => [
                    'raw_request'  => $result->rawRequest,
                    'raw_response' => $result->rawResponse,
                ],
            ]);

            ShipmentTrackingEvent::query()->firstOrCreate(
                [
                    'shipment_id' => $shipment->id,
                    'event_key'   => 'created-' . $result->consignmentId,
                ],
                [
                    'courier'         => $shipment->courier,
                    'consignment_id'  => $result->consignmentId,
                    'external_status' => $result->externalStatus,
                    'internal_status' => $result->internalStatus,
                    'description'     => "Shipment created with {$shipment->courier} (Consignment #{$result->consignmentId})",
                    'raw_payload'     => $result->rawResponse,
                    'occurred_at'     => now(),
                    'created_at'      => now(),
                ]
            );

            // Transition order to 'shipped' (commits stock reservation via OrderService -> InventoryServiceContract)
            if (in_array($order->status, ['confirmed', 'processing'], true)) {
                $this->orderService->shipOrder(
                    orderOrId: $order,
                    shipmentMetadata: [
                        'courier'         => $shipment->courier,
                        'consignment_id'  => $result->consignmentId,
                        'tracking_number' => $result->trackingNumber ?? $result->consignmentId,
                    ],
                    actorId: $actorId,
                    notes: "Dispatched via {$shipment->courier} ({$result->consignmentId})"
                );
            }

            return $shipment->fresh(['trackingEvents', 'order']) ?? $shipment;
        });

        event(new ShipmentCreated(
            shipmentId: $updatedShipment->id,
            orderId: $order->id,
            orderNumber: $order->order_number,
            courier: $updatedShipment->courier,
            consignmentId: (string) $updatedShipment->consignment_id,
            actorId: $actorId
        ));

        return [
            'shipment'          => $updatedShipment,
            'idempotent_replay' => false,
        ];
    }

    /**
     * Cancel a consignment before pickup/delivery.
     */
    public function cancelShipment(
        int|Shipment $shipmentOrId,
        string $reason,
        ?int $actorId = null
    ): Shipment {
        $shipment = $shipmentOrId instanceof Shipment
            ? $shipmentOrId
            : Shipment::query()->findOrFail((int) $shipmentOrId);

        if ($shipment->status === 'cancelled') {
            return $shipment->load(['trackingEvents', 'order']);
        }

        if (!in_array($shipment->status, self::CANCELLABLE_STATUSES, true)) {
            throw new InvalidShipmentStateException(
                "Shipment #{$shipment->id} in status '{$shipment->status}' cannot be cancelled.",
                ['current_status' => $shipment->status]
            );
        }

        if ($shipment->consignment_id !== null && $shipment->consignment_id !== '') {
            $provider = $this->providerFactory->resolve($shipment->courier);
            $cancelResult = $provider->cancelShipment($shipment->consignment_id, $reason);

            if (!$cancelResult->successful) {
                throw new CourierApiException(
                    $cancelResult->errorMessage ?? "Courier '{$shipment->courier}' rejected shipment cancellation."
                );
            }
        }

        return DB::transaction(function () use ($shipment, $reason) {
            $shipment->update([
                'status'                    => 'cancelled',
                'cancelled_at'              => now(),
                'cod_reconciliation_status' => 'not_applicable',
            ]);

            ShipmentTrackingEvent::query()->firstOrCreate(
                [
                    'shipment_id' => $shipment->id,
                    'event_key'   => 'cancelled-' . ($shipment->consignment_id ?? $shipment->id),
                ],
                [
                    'courier'         => $shipment->courier,
                    'consignment_id'  => $shipment->consignment_id,
                    'external_status' => 'Cancelled',
                    'internal_status' => 'cancelled',
                    'description'     => "Shipment cancelled: {$reason}",
                    'occurred_at'     => now(),
                    'created_at'      => now(),
                ]
            );

            return $shipment->fresh(['trackingEvents', 'order']) ?? $shipment;
        });
    }

    /**
     * Pull latest tracking status from the courier API and apply any status transition.
     */
    public function syncTracking(int|Shipment $shipmentOrId): Shipment
    {
        $shipment = $shipmentOrId instanceof Shipment
            ? $shipmentOrId
            : Shipment::query()->findOrFail((int) $shipmentOrId);

        if (!$shipment->consignment_id) {
            throw new InvalidShipmentStateException("Shipment #{$shipment->id} has no consignment_id to track.");
        }

        $provider = $this->providerFactory->resolve($shipment->courier);
        $trackingResult = $provider->trackShipment((string) $shipment->consignment_id);

        if (!$trackingResult->successful) {
            throw new CourierApiException(
                $trackingResult->errorMessage ?? "Tracking lookup failed for consignment '{$shipment->consignment_id}'."
            );
        }

        $eventKey = 'poll-' . $shipment->consignment_id . '-' . strtolower($trackingResult->externalStatus);

        $this->applyTrackingUpdate(
            shipment: $shipment,
            eventKey: $eventKey,
            externalStatus: $trackingResult->externalStatus,
            internalStatus: $trackingResult->internalStatus,
            description: $trackingResult->description,
            location: $trackingResult->location,
            collectedCodPaisa: $trackingResult->collectedCodAmountPaisa,
            rawPayload: $trackingResult->rawResponse
        );

        return $shipment->fresh(['trackingEvents', 'order']) ?? $shipment;
    }

    /**
     * Process an incoming courier tracking webhook idempotently.
     *
     * @return array{shipment: Shipment, event: ShipmentTrackingEvent, idempotent_replay: bool}
     */
    public function processWebhook(
        string $courier,
        array $payload,
        array $headers = [],
        string $rawBody = ''
    ): array {
        $provider = $this->providerFactory->resolve($courier);
        $courierName = $provider->providerName();

        $webhookData = $provider->parseTrackingWebhook($payload, $headers, $rawBody);

        /** @var Shipment|null $shipment */
        $shipment = Shipment::query()
            ->where('courier', $courierName)
            ->where(function ($q) use ($webhookData) {
                $q->where('consignment_id', $webhookData->consignmentId);
                if ($webhookData->merchantOrderReference !== null && $webhookData->merchantOrderReference !== '') {
                    $q->orWhere('merchant_order_reference', $webhookData->merchantOrderReference);
                }
            })
            ->latest('id')
            ->first();

        if (!$shipment) {
            throw new ShippingException(
                "Shipment for consignment '{$webhookData->consignmentId}' on courier '{$courierName}' was not found.",
                404,
                'consignment_id'
            );
        }

        $this->auditLogger->log(
            courier: $courierName,
            operation: 'webhook',
            httpMethod: 'POST',
            endpoint: "/api/webhooks/shipping/{$courierName}",
            responseStatus: 200,
            requestPayload: $payload,
            responsePayload: ['event_key' => $webhookData->eventKey, 'internal_status' => $webhookData->internalStatus],
            orderId: $shipment->order_id,
            shipmentId: $shipment->id
        );

        // Idempotency check on (shipment_id, event_key)
        $existingEvent = ShipmentTrackingEvent::query()
            ->where('shipment_id', $shipment->id)
            ->where('event_key', $webhookData->eventKey)
            ->first();

        if ($existingEvent !== null) {
            return [
                'shipment'          => $shipment->load(['trackingEvents', 'order']),
                'event'             => $existingEvent,
                'idempotent_replay' => true,
            ];
        }

        $applied = $this->applyTrackingUpdate(
            shipment: $shipment,
            eventKey: $webhookData->eventKey,
            externalStatus: $webhookData->externalStatus,
            internalStatus: $webhookData->internalStatus,
            description: $webhookData->description,
            location: $webhookData->location,
            collectedCodPaisa: $webhookData->collectedCodAmountPaisa,
            rawPayload: $webhookData->rawPayload
        );

        return [
            'shipment'          => $shipment->fresh(['trackingEvents', 'order']) ?? $shipment,
            'event'             => $applied['event'],
            'idempotent_replay' => $applied['idempotent_replay'],
        ];
    }

    /**
     * Reconcile COD collections from a courier settlement report against delivered COD shipments.
     *
     * @param array<int, array{consignment_id?: string, merchant_order_reference?: string, collected_amount: int}> $settlementItems
     */
    public function reconcileCodCollections(
        array $settlementItems,
        ?string $courier = null,
        ?int $actorId = null
    ): array {
        $reconciledCount = 0;
        $discrepancyCount = 0;
        $totalExpectedPaisa = 0;
        $totalCollectedPaisa = 0;
        $processedItems = [];

        foreach ($settlementItems as $item) {
            $consignmentId = isset($item['consignment_id']) ? trim((string) $item['consignment_id']) : '';
            $merchantRef = isset($item['merchant_order_reference']) ? trim((string) $item['merchant_order_reference']) : '';
            $collectedPaisa = (int) ($item['collected_amount'] ?? 0);

            $query = Shipment::query()->with('order');
            if ($courier !== null && trim($courier) !== '') {
                $query->where('courier', strtolower(trim($courier)));
            }

            $query->where(function ($q) use ($consignmentId, $merchantRef) {
                if ($consignmentId !== '') {
                    $q->where('consignment_id', $consignmentId);
                }
                if ($merchantRef !== '') {
                    $q->orWhere('merchant_order_reference', $merchantRef);
                }
            });

            /** @var Shipment|null $shipment */
            $shipment = $query->latest('id')->first();
            if (!$shipment) {
                continue;
            }

            $expectedPaisa = (int) $shipment->cod_amount;
            $totalExpectedPaisa += $expectedPaisa;
            $totalCollectedPaisa += $collectedPaisa;

            $isExactMatch = $expectedPaisa === $collectedPaisa;
            $reconciliationStatus = $isExactMatch ? 'reconciled' : 'discrepancy';

            if ($isExactMatch) {
                $reconciledCount++;
            } else {
                $discrepancyCount++;
            }

            DB::transaction(function () use ($shipment, $expectedPaisa, $collectedPaisa, $reconciliationStatus, $actorId) {
                $lockedShipment = Shipment::query()
                    ->where('id', $shipment->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $meta = $lockedShipment->courier_metadata ?? [];
                if ($reconciliationStatus === 'discrepancy') {
                    $meta['cod_discrepancy'] = [
                        'expected_amount_paisa'  => $expectedPaisa,
                        'collected_amount_paisa' => $collectedPaisa,
                        'difference_paisa'       => $collectedPaisa - $expectedPaisa,
                        'flagged_by'             => $actorId,
                        'flagged_at'             => now()->toIso8601String(),
                    ];
                }

                $lockedShipment->update([
                    'cod_collected_amount'      => $collectedPaisa,
                    'cod_reconciliation_status' => $reconciliationStatus,
                    'cod_reconciled_at'         => now(),
                    'courier_metadata'          => $meta,
                ]);

                if ($reconciliationStatus === 'reconciled' && $lockedShipment->order) {
                    $order = Order::query()->where('id', $lockedShipment->order_id)->lockForUpdate()->first();
                    if ($order && $order->payment_method === 'cod' && $order->payment_status !== 'paid') {
                        $prevPay = $order->payment_status;
                        $order->update(['payment_status' => 'paid']);

                        PaymentTransaction::query()
                            ->where('order_id', $order->id)
                            ->where('gateway', 'cod')
                            ->update([
                                'status'  => 'paid',
                                'paid_at' => now(),
                            ]);

                        $this->orderService->recordStatusHistory(
                            order: $order,
                            fromStatus: $order->status,
                            toStatus: $order->status,
                            fromPaymentStatus: $prevPay,
                            toPaymentStatus: 'paid',
                            actorType: 'staff',
                            actorId: $actorId,
                            reason: "COD collection reconciled ({$collectedPaisa} paisa)"
                        );
                    }
                }
            });

            event(new CodCollectionReconciled(
                shipmentId: $shipment->id,
                orderId: $shipment->order_id,
                orderNumber: $shipment->merchant_order_reference,
                courier: $shipment->courier,
                consignmentId: (string) $shipment->consignment_id,
                expectedAmountPaisa: $expectedPaisa,
                collectedAmountPaisa: $collectedPaisa,
                reconciliationStatus: $reconciliationStatus
            ));

            $processedItems[] = [
                'shipment_id'               => $shipment->id,
                'order_number'              => $shipment->merchant_order_reference,
                'courier'                   => $shipment->courier,
                'consignment_id'            => $shipment->consignment_id,
                'expected_cod_amount'       => $expectedPaisa,
                'collected_cod_amount'      => $collectedPaisa,
                'difference_amount'         => $collectedPaisa - $expectedPaisa,
                'cod_reconciliation_status' => $reconciliationStatus,
            ];
        }

        return [
            'currency'               => 'BDT',
            'reconciled_count'       => $reconciledCount,
            'discrepancy_count'      => $discrepancyCount,
            'total_expected_amount'  => $totalExpectedPaisa,
            'total_collected_amount' => $totalCollectedPaisa,
            'items'                  => $processedItems,
        ];
    }

    /**
     * Get COD reconciliation summary & pending/discrepancy shipments.
     */
    public function getCodReconciliationReport(array $filters = []): array
    {
        $query = Shipment::query()
            ->with('order')
            ->where('cod_amount', '>', 0);

        if (!empty($filters['courier'])) {
            $query->where('courier', strtolower((string) $filters['courier']));
        }

        if (!empty($filters['reconciliation_status'])) {
            $query->where('cod_reconciliation_status', strtolower((string) $filters['reconciliation_status']));
        }

        $shipments = $query->orderByDesc('id')->get();

        return [
            'currency'                => 'BDT',
            'total_cod_shipments'     => $shipments->count(),
            'pending_count'           => $shipments->where('cod_reconciliation_status', 'pending')->count(),
            'reconciled_count'        => $shipments->where('cod_reconciliation_status', 'reconciled')->count(),
            'discrepancy_count'       => $shipments->where('cod_reconciliation_status', 'discrepancy')->count(),
            'total_expected_amount'   => (int) $shipments->sum('cod_amount'),
            'total_collected_amount'  => (int) $shipments->sum('cod_collected_amount'),
            'shipments'               => $shipments->map(fn (Shipment $s) => $this->formatShipment($s))->all(),
        ];
    }

    /**
     * Paginated admin shipments listing.
     */
    public function adminList(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Shipment::query()->with(['trackingEvents', 'order']);

        if (!empty($filters['courier'])) {
            $query->where('courier', strtolower((string) $filters['courier']));
        }

        if (!empty($filters['status'])) {
            $query->where('status', strtolower((string) $filters['status']));
        }

        if (!empty($filters['cod_reconciliation_status'])) {
            $query->where('cod_reconciliation_status', strtolower((string) $filters['cod_reconciliation_status']));
        }

        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('consignment_id', 'like', "%{$search}%")
                    ->orWhere('merchant_order_reference', 'like', "%{$search}%")
                    ->orWhere('tracking_number', 'like', "%{$search}%");
            });
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    /**
     * Format a Shipment with its tracking events for API responses.
     */
    public function formatShipment(Shipment $shipment): array
    {
        $shipment->loadMissing(['trackingEvents']);
        $currency = $shipment->currency ?? 'BDT';

        $shippingCharge = new Money((int) $shipment->shipping_charge, $currency);
        $codAmount = new Money((int) $shipment->cod_amount, $currency);
        $codCollected = new Money((int) $shipment->cod_collected_amount, $currency);

        return [
            'id'                        => $shipment->id,
            'order_id'                  => $shipment->order_id,
            'merchant_order_reference'  => $shipment->merchant_order_reference,
            'courier'                   => $shipment->courier,
            'consignment_id'            => $shipment->consignment_id,
            'tracking_number'           => $shipment->tracking_number,
            'tracking_url'              => $shipment->tracking_url,
            'status'                    => $shipment->status,
            'shipping_charge'           => $shippingCharge->toArray(),
            'shipping_charge_amount'    => $shippingCharge->amount,
            'cod_amount'                => $codAmount->toArray(),
            'cod_amount_paisa'          => $codAmount->amount,
            'cod_collected'             => $codCollected->toArray(),
            'cod_collected_amount'      => $codCollected->amount,
            'cod_reconciliation_status' => $shipment->cod_reconciliation_status,
            'cod_reconciled_at'         => $shipment->cod_reconciled_at?->toIso8601String(),
            'currency'                  => $currency,
            'weight_grams'              => (int) $shipment->weight_grams,
            'item_quantity'             => (int) $shipment->item_quantity,
            'recipient_snapshot'        => $shipment->recipient_snapshot,
            'failure_reason'            => $shipment->failure_reason,
            'picked_up_at'              => $shipment->picked_up_at?->toIso8601String(),
            'delivered_at'              => $shipment->delivered_at?->toIso8601String(),
            'failed_at'                 => $shipment->failed_at?->toIso8601String(),
            'returned_at'               => $shipment->returned_at?->toIso8601String(),
            'cancelled_at'              => $shipment->cancelled_at?->toIso8601String(),
            'inventory_restored_at'     => $shipment->inventory_restored_at?->toIso8601String(),
            'created_at'                => $shipment->created_at?->toIso8601String(),
            'updated_at'                => $shipment->updated_at?->toIso8601String(),
            'tracking_events'           => $shipment->trackingEvents->map(fn (ShipmentTrackingEvent $e) => [
                'id'              => $e->id,
                'event_key'       => $e->event_key,
                'external_status' => $e->external_status,
                'internal_status' => $e->internal_status,
                'description'     => $e->description,
                'location'        => $e->location,
                'occurred_at'     => $e->occurred_at?->toIso8601String(),
            ])->all(),
        ];
    }

    /**
     * Apply a tracking status update atomically and trigger delivery / return side-effects.
     *
     * @return array{event: ShipmentTrackingEvent, idempotent_replay: bool}
     */
    private function applyTrackingUpdate(
        Shipment $shipment,
        string $eventKey,
        string $externalStatus,
        string $internalStatus,
        ?string $description,
        ?string $location,
        ?int $collectedCodPaisa,
        array $rawPayload = []
    ): array {
        $idempotentReplay = false;
        $deliveredEvent = null;
        $returnedEvent = null;

        /** @var ShipmentTrackingEvent $trackingEvent */
        $trackingEvent = DB::transaction(function () use (
            $shipment,
            $eventKey,
            $externalStatus,
            $internalStatus,
            $description,
            $location,
            $collectedCodPaisa,
            $rawPayload,
            &$idempotentReplay,
            &$deliveredEvent,
            &$returnedEvent
        ) {
            /** @var Shipment $lockedShipment */
            $lockedShipment = Shipment::query()
                ->with('order.items')
                ->where('id', $shipment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = ShipmentTrackingEvent::query()
                ->where('shipment_id', $lockedShipment->id)
                ->where('event_key', $eventKey)
                ->first();

            if ($existing !== null) {
                $idempotentReplay = true;
                return $existing;
            }

            try {
                $eventRow = ShipmentTrackingEvent::query()->create([
                    'shipment_id'     => $lockedShipment->id,
                    'courier'         => $lockedShipment->courier,
                    'consignment_id'  => $lockedShipment->consignment_id,
                    'event_key'       => $eventKey,
                    'external_status' => $externalStatus,
                    'internal_status' => $internalStatus,
                    'description'     => $description,
                    'location'        => $location,
                    'raw_payload'     => $rawPayload,
                    'occurred_at'     => now(),
                    'created_at'      => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                $idempotentReplay = true;
                return ShipmentTrackingEvent::query()
                    ->where('shipment_id', $lockedShipment->id)
                    ->where('event_key', $eventKey)
                    ->firstOrFail();
            }

            $updates = [
                'status' => $internalStatus,
            ];

            if ($internalStatus === 'picked_up' && $lockedShipment->picked_up_at === null) {
                $updates['picked_up_at'] = now();
            }

            if ($internalStatus === 'failed') {
                $updates['failed_at'] = now();
                $updates['failure_reason'] = $description !== null ? substr($description, 0, 191) : 'Delivery failed';
            }

            if ($internalStatus === 'delivered') {
                $updates['delivered_at'] = $lockedShipment->delivered_at ?? now();

                if ($collectedCodPaisa !== null && (int) $lockedShipment->cod_amount > 0) {
                    $updates['cod_collected_amount'] = $collectedCodPaisa;
                    $updates['cod_reconciliation_status'] = $collectedCodPaisa === (int) $lockedShipment->cod_amount
                        ? 'reconciled'
                        : 'discrepancy';
                    $updates['cod_reconciled_at'] = now();
                }

                if ($lockedShipment->order && $lockedShipment->order->status === 'shipped') {
                    $this->orderService->deliverOrder(
                        orderOrId: $lockedShipment->order,
                        notes: "Delivered via {$lockedShipment->courier} ({$lockedShipment->consignment_id})"
                    );
                }

                $deliveredEvent = new ShipmentDelivered(
                    shipmentId: $lockedShipment->id,
                    orderId: $lockedShipment->order_id,
                    orderNumber: $lockedShipment->merchant_order_reference,
                    courier: $lockedShipment->courier,
                    consignmentId: (string) $lockedShipment->consignment_id
                );
            }

            if ($internalStatus === 'returned') {
                $updates['returned_at'] = $lockedShipment->returned_at ?? now();
                $updates['cod_reconciliation_status'] = 'not_applicable';

                // Restore inventory idempotently (only once per returned shipment!)
                if ($lockedShipment->inventory_restored_at === null && $lockedShipment->order) {
                    $this->restoreInventoryForReturnedOrder($lockedShipment, $lockedShipment->order);
                    $updates['inventory_restored_at'] = now();
                }

                if ($lockedShipment->order && $lockedShipment->order->status !== 'returned') {
                    $this->orderService->markOrderReturned(
                        orderOrId: $lockedShipment->order,
                        reason: $description ?? "Parcel returned by {$lockedShipment->courier}",
                        actorType: 'webhook'
                    );
                }

                $returnedEvent = new ParcelReturned(
                    shipmentId: $lockedShipment->id,
                    orderId: $lockedShipment->order_id,
                    orderNumber: $lockedShipment->merchant_order_reference,
                    courier: $lockedShipment->courier,
                    consignmentId: (string) $lockedShipment->consignment_id,
                    reason: $description
                );
            }

            $lockedShipment->update($updates);

            return $eventRow;
        });

        if ($deliveredEvent !== null) {
            event($deliveredEvent);
        }

        if ($returnedEvent !== null) {
            event($returnedEvent);
        }

        return [
            'event'             => $trackingEvent,
            'idempotent_replay' => $idempotentReplay,
        ];
    }

    /**
     * Restore inventory `quantity_on_hand` via `InventoryServiceContract::adjustStock` (`type: 'return'`)
     * when a shipped parcel is returned to the merchant.
     */
    private function restoreInventoryForReturnedOrder(Shipment $shipment, Order $order): void
    {
        $order->loadMissing('items');

        // Determine warehouse mapping from the original stock reservation if available
        $warehouseByVariant = [];
        if ($order->stock_reservation_id !== null) {
            $reservation = $this->inventoryService->getReservation($order->stock_reservation_id);
            if ($reservation !== null) {
                foreach ($reservation->items as $resItem) {
                    $warehouseByVariant[$resItem->productVariantId] = $resItem->warehouseId;
                }
            }
        }

        foreach ($order->items as $orderItem) {
            $variantId = (int) $orderItem->product_variant_id;
            if ($variantId <= 0) {
                continue;
            }

            $warehouseId = $warehouseByVariant[$variantId] ?? null;
            if ($warehouseId === null) {
                $summary = $this->inventoryService->getStockSummaryForVariant($variantId);
                $warehouseId = $summary->warehouseBreakdown[0]['warehouse_id'] ?? null;
            }

            if ($warehouseId === null) {
                continue;
            }

            $this->inventoryService->adjustStock(new StockAdjustmentData(
                productVariantId: $variantId,
                warehouseId: (int) $warehouseId,
                quantityDelta: (int) $orderItem->quantity,
                type: 'return',
                reason: "Returned parcel #{$shipment->consignment_id} for order {$order->order_number}",
                notes: "Courier: {$shipment->courier}",
                referenceType: 'shipment',
                referenceId: (string) $shipment->id
            ));
        }
    }
}
