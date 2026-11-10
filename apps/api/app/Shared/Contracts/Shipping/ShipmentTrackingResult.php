<?php

namespace App\Shared\Contracts\Shipping;

final readonly class ShipmentTrackingResult
{
    /**
     * @param array<int, array{external_status: string, internal_status: string, description: ?string, location: ?string, occurred_at: ?string}> $events
     */
    public function __construct(
        public bool $successful,
        public string $courier,
        public string $consignmentId,
        public string $externalStatus,
        public string $internalStatus,
        public ?string $description = null,
        public ?string $location = null,
        public ?int $collectedCodAmountPaisa = null,
        public array $events = [],
        public array $rawRequest = [],
        public array $rawResponse = [],
        public ?string $errorMessage = null,
    ) {}
}

