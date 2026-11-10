<?php

namespace App\Shared\Contracts\Shipping;

final readonly class CancelShipmentResult
{
    public function __construct(
        public bool $successful,
        public string $courier,
        public string $consignmentId,
        public string $externalStatus,
        public string $internalStatus,
        public array $rawRequest = [],
        public array $rawResponse = [],
        public ?string $errorMessage = null,
    ) {}
}

