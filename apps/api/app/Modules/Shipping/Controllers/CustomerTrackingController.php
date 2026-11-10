<?php

namespace App\Modules\Shipping\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Policies\OrderPolicy;
use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Services\ShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CustomerTrackingController extends Controller
{
    public function __construct(
        protected ShipmentService $shipmentService,
        protected OrderPolicy $orderPolicy,
    ) {}

    /**
     * GET /api/orders/{orderNumber}/tracking
     * Returns shipment & tracking history for an order owned by the authenticated customer.
     */
    public function trackOrder(Request $request, string $orderNumber): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user('sanctum') ?? $request->user();

        $order = Order::query()
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        if (!$this->orderPolicy->view($user, $order)) {
            return response()->json([
                'message' => 'You are not authorized to view tracking for this order.',
            ], 403);
        }

        $shipments = Shipment::query()
            ->with('trackingEvents')
            ->where('order_id', $order->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (Shipment $s) => $this->shipmentService->formatShipment($s))
            ->all();

        return response()->json([
            'data' => [
                'order_number'      => $order->order_number,
                'order_status'      => $order->status,
                'fulfilment_status' => $order->fulfilment_status,
                'shipments'         => $shipments,
            ],
        ]);
    }

    /**
     * POST /api/webhooks/shipping/{courier}
     * Processes asynchronous courier tracking webhooks idempotently.
     */
    public function webhook(Request $request, string $courier): JsonResponse
    {
        $headers = $request->headers->all();

        $result = $this->shipmentService->processWebhook(
            courier: $courier,
            payload: $request->all(),
            headers: $headers,
            rawBody: (string) $request->getContent()
        );

        return response()->json([
            'data' => [
                'shipment'       => $this->shipmentService->formatShipment($result['shipment']),
                'tracking_event' => [
                    'id'              => $result['event']->id,
                    'event_key'       => $result['event']->event_key,
                    'external_status' => $result['event']->external_status,
                    'internal_status' => $result['event']->internal_status,
                    'description'     => $result['event']->description,
                ],
            ],
            'idempotent_replay' => $result['idempotent_replay'],
        ]);
    }
}

