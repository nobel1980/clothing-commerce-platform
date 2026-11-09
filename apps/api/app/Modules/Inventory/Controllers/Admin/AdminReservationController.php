<?php

namespace App\Modules\Inventory\Controllers\Admin;

use App\Modules\Inventory\Models\StockReservation;
use App\Modules\Inventory\Requests\CreateReservationRequest;
use App\Modules\Inventory\Requests\ReleaseReservationRequest;
use App\Modules\Inventory\Resources\StockReservationResource;
use App\Modules\Inventory\Services\ReservationService;
use App\Shared\Contracts\Inventory\ReserveStockData;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class AdminReservationController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected ReservationService $reservationService
    ) {}

    /**
     * GET /api/admin/inventory/reservations
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', StockReservation::class);

        $reservations = $this->reservationService->list(
            filters: $request->only(['status', 'reference_type', 'reference_id', 'idempotency_key']),
            perPage: min(max((int) $request->input('per_page', 20), 1), 100)
        );

        return StockReservationResource::collection($reservations);
    }

    /**
     * POST /api/admin/inventory/reservations
     */
    public function store(CreateReservationRequest $request): JsonResponse
    {
        $this->authorize('manage', StockReservation::class);

        $validated = $request->validated();

        $reservation = $this->reservationService->reserve(new ReserveStockData(
            idempotencyKey: $validated['idempotency_key'],
            items: $validated['items'],
            ttlMinutes: (int) ($validated['ttl_minutes'] ?? 15),
            referenceType: $validated['reference_type'] ?? 'admin_reservation',
            referenceId: $validated['reference_id'] ?? null,
            metadata: $validated['metadata'] ?? null,
            performedBy: $request->user()?->id,
        ));

        return (new StockReservationResource($reservation))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/admin/inventory/reservations/{reservation}
     */
    public function show(StockReservation $reservation): StockReservationResource
    {
        $this->authorize('viewAny', StockReservation::class);

        return new StockReservationResource(
            $reservation->load(['items.productVariant', 'items.warehouse'])
        );
    }

    /**
     * POST /api/admin/inventory/reservations/{reservation}/commit
     */
    public function commit(Request $request, StockReservation $reservation): StockReservationResource
    {
        $this->authorize('manage', StockReservation::class);

        $committed = $this->reservationService->commit($reservation->id, $request->user()?->id);

        return new StockReservationResource($committed);
    }

    /**
     * POST /api/admin/inventory/reservations/{reservation}/release
     */
    public function release(ReleaseReservationRequest $request, StockReservation $reservation): StockReservationResource
    {
        $this->authorize('manage', StockReservation::class);

        $released = $this->reservationService->release(
            $reservation->id,
            $request->validated('reason'),
            $request->user()?->id
        );

        return new StockReservationResource($released);
    }

    /**
     * POST /api/admin/inventory/reservations/expire-overdue
     */
    public function expireOverdue(): JsonResponse
    {
        $this->authorize('manage', StockReservation::class);

        $expiredCount = $this->reservationService->expireOverdue();

        return response()->json([
            'message'       => "Expired {$expiredCount} overdue reservation(s).",
            'expired_count' => $expiredCount,
        ]);
    }
}

