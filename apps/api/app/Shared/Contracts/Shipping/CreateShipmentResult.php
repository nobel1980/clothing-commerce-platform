<?php

namespace App\Shared\Contracts\Shipping;

final readonly class CreateShipmentResult
{
    public function __construct(
        public bool $successful,
        public string $courier,
        public ?string $consignmentId,
        public ?string $trackingNumber,
        public ?string $trackingUrl,
        public string $externalStatus,
        public string $internalStatus,
        public int $shippingChargePaisa = 0,
        public array $rawRequest = [],
        public array $rawResponse = [],
        public ?string $errorMessage = null,
    ) {}
}

