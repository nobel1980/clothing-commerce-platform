<?php

namespace App\Modules\Shipping\Controllers;

use App\Modules\Orders\Models\Order;
use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Services\ShipmentService;
use App\Shared\Contracts\Shipping\DeliveryQuoteRequest;
use App\Shared\Support\BangladeshAddressValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminShipmentController extends Controller
{
    public function __construct(
        protected ShipmentService $shipmentService
    ) {}

    private function authorizePermission(Request $request, string $permission): void
    {
        $user = $request->user('sanctum') ?? $request->user();

        abort_unless(
            $user !== null && $user->can($permission),
            403,
            'You do not have permission to perform this shipping action.'
        );
    }

    /**
     * GET /api/admin/shipments
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'shipping.view');

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $paginator = $this->shipmentService->adminList($request->only([
            'courier',
            'status',
            'cod_reconciliation_status',
            'search',
        ]), $perPage);

        $items = collect($paginator->items())
            ->map(fn (Shipment $s) => $this->shipmentService->formatShipment($s))
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
     * GET /api/admin/shipments/{shipment}
     */
    public function show(Request $request, Shipment $shipment): JsonResponse
    {
        $this->authorizePermission($request, 'shipping.view');

        return response()->json([
            'data' => $this->shipmentService->formatShipment($shipment),
        ]);
    }

    /**
     * POST /api/admin/shipments/calculate-charge
     */
    public function calculateCharge(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'shipping.view');

        $validated = $request->validate([
            'courier'             => ['nullable', 'string', Rule::in(['pathao', 'steadfast', 'redx'])],
            'division'            => ['required', 'string', Rule::in(BangladeshAddressValidator::DIVISIONS)],
            'district'            => ['required', 'string', 'max:100'],
            'upazila_thana'       => ['nullable', 'string', 'max:100'],
            'weight_grams'        => ['nullable', 'integer', 'min:100', 'max:50000'],
            'cod_amount'          => ['nullable', 'integer', 'min:0'],
            'delivery_type'       => ['nullable', 'string', Rule::in(['standard', 'express'])],
            'recipient_city_id'   => ['nullable', 'integer', 'min:1'],
            'recipient_zone_id'   => ['nullable', 'integer', 'min:1'],
        ]);

        $quoteRequest = new DeliveryQuoteRequest(
            recipientDivision: $validated['division'],
            recipientDistrict: $validated['district'],
            recipientUpazila: $validated['upazila_thana'] ?? null,
            weightGrams: (int) ($validated['weight_grams'] ?? 500),
            codAmountPaisa: (int) ($validated['cod_amount'] ?? 0),
            deliveryType: (string) ($validated['delivery_type'] ?? 'standard'),
            recipientCityId: isset($validated['recipient_city_id']) ? (int) $validated['recipient_city_id'] : null,
            recipientZoneId: isset($validated['recipient_zone_id']) ? (int) $validated['recipient_zone_id'] : null
        );

        $result = $this->shipmentService->calculateDeliveryCharge(
            request: $quoteRequest,
            courier: $validated['courier'] ?? null
        );

        return response()->json([
            'data' => [
                'courier'               => $result->courier,
                'delivery_charge_amount'=> $result->deliveryChargePaisa,
                'cod_fee_amount'        => $result->codFeePaisa,
                'total_charge_amount'   => $result->totalChargePaisa,
                'currency'              => $result->currency,
                'estimated_days'        => $result->estimatedDays,
            ],
        ]);
    }

    /**
     * POST /api/admin/shipments
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'shipping.manage');

        $validated = $request->validate([
            'order_id'            => ['required_without:order_number', 'nullable', 'integer', 'min:1'],
            'order_number'        => ['required_without:order_id', 'nullable', 'string', 'max:40'],
            'courier'             => ['nullable', 'string', Rule::in(['pathao', 'steadfast', 'redx'])],
            'idempotency_key'     => ['nullable', 'string', 'max:128'],
            'weight_grams'        => ['nullable', 'integer', 'min:100', 'max:50000'],
            'item_description'    => ['nullable', 'string', 'max:191'],
            'special_instruction' => ['nullable', 'string', 'max:500'],
            'recipient_city_id'   => ['nullable', 'integer', 'min:1'],
            'recipient_zone_id'   => ['nullable', 'integer', 'min:1'],
            'recipient_area_id'   => ['nullable', 'integer', 'min:1'],
        ]);

        $order = !empty($validated['order_id'])
            ? Order::query()->findOrFail((int) $validated['order_id'])
            : Order::query()->where('order_number', $validated['order_number'])->firstOrFail();

        $courier = (string) ($validated['courier'] ?? config('shipping.default_courier', 'pathao'));
        $idempotencyKey = $request->header('Idempotency-Key')
            ?? $request->header('X-Idempotency-Key')
            ?? $validated['idempotency_key']
            ?? ('ship-' . $order->order_number . '-' . $courier);

        $actorId = ($request->user('sanctum') ?? $request->user())?->id;

        $result = $this->shipmentService->createShipment(
            orderOrId: $order,
            courier: $courier,
            idempotencyKey: (string) $idempotencyKey,
            options: $validated,
            actorId: $actorId
        );

        $statusCode = $result['idempotent_replay'] ? 200 : 201;

        return response()->json([
            'data'              => $this->shipmentService->formatShipment($result['shipment']),
            'idempotent_replay' => $result['idempotent_replay'],
        ], $statusCode);
    }

    /**
     * POST /api/admin/shipments/{shipment}/cancel
     */
    public function cancel(Request $request, Shipment $shipment): JsonResponse
    {
        $this->authorizePermission($request, 'shipping.manage');

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:191'],
        ]);

        $actorId = ($request->user('sanctum') ?? $request->user())?->id;

        $updated = $this->shipmentService->cancelShipment(
            shipmentOrId: $shipment,
            reason: $validated['reason'],
            actorId: $actorId
        );

        return response()->json([
            'data' => $this->shipmentService->formatShipment($updated),
        ]);
    }

    /**
     * POST /api/admin/shipments/{shipment}/track
     */
    public function track(Request $request, Shipment $shipment): JsonResponse
    {
        $this->authorizePermission($request, 'shipping.view');

        $updated = $this->shipmentService->syncTracking($shipment);

        return response()->json([
            'data' => $this->shipmentService->formatShipment($updated),
        ]);
    }

    /**
     * GET /api/admin/shipments/cod-reconciliation
     */
    public function codReconciliationReport(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'shipping.view');

        $report = $this->shipmentService->getCodReconciliationReport(
            $request->only(['courier', 'reconciliation_status'])
        );

        return response()->json([
            'data' => $report,
        ]);
    }

    /**
     * POST /api/admin/shipments/cod-reconciliation
     */
    public function reconcileCod(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'shipping.manage');

        $validated = $request->validate([
            'courier'                          => ['nullable', 'string', Rule::in(['pathao', 'steadfast', 'redx'])],
            'items'                            => ['required', 'array', 'min:1'],
            'items.*.consignment_id'           => ['required_without:items.*.merchant_order_reference', 'nullable', 'string', 'max:128'],
            'items.*.merchant_order_reference' => ['required_without:items.*.consignment_id', 'nullable', 'string', 'max:64'],
            'items.*.collected_amount'         => ['required', 'integer', 'min:0'],
        ]);

        $actorId = ($request->user('sanctum') ?? $request->user())?->id;

        $summary = $this->shipmentService->reconcileCodCollections(
            settlementItems: $validated['items'],
            courier: $validated['courier'] ?? null,
            actorId: $actorId
        );

        return response()->json([
            'data' => $summary,
        ]);
    }
}

