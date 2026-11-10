<?php

namespace App\Shared\Contracts\Shipping;

/**
 * Contract for Bangladesh courier provider adapters (Pathao, Steadfast, REDX).
 */
interface CourierProvider
{
    /**
     * Canonical lowercase identifier for this courier ('pathao', 'steadfast', 'redx').
     */
    public function providerName(): string;

    /**
     * Calculate delivery charge for a parcel (in integer BDT paisa).
     */
    public function calculateDeliveryCharge(DeliveryQuoteRequest $request): DeliveryChargeResult;

    /**
     * Create a shipment/consignment with the courier provider.
     */
    public function createShipment(CreateShipmentRequest $request): CreateShipmentResult;

    /**
     * Cancel a consignment before pickup/dispatch.
     */
    public function cancelShipment(string $consignmentId, ?string $reason = null): CancelShipmentResult;

    /**
     * Fetch current tracking status and events for a consignment from the courier API.
     */
    public function trackShipment(string $consignmentId): ShipmentTrackingResult;

    /**
     * Validate and parse an incoming tracking webhook notification into a normalized payload.
     */
    public function parseTrackingWebhook(
        array $payload,
        array $headers = [],
        string $rawBody = ''
    ): TrackingWebhookPayload;

    /**
     * Map a provider-specific external status string to our internal shipment status:
     * 'created' | 'picked_up' | 'in_transit' | 'out_for_delivery' | 'delivered' | 'failed' | 'returning' | 'returned' | 'cancelled'
     */
    public function mapExternalStatus(string $externalStatus): string;
}

