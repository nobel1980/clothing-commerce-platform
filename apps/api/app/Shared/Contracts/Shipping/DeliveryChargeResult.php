<?php

namespace App\Shared\Contracts\Shipping;

final readonly class DeliveryChargeResult
{
    public function __construct(
        public bool $successful,
        public string $courier,
        public int $deliveryChargePaisa,
        public int $codFeePaisa = 0,
        public int $totalChargePaisa = 0,
        public string $currency = 'BDT',
        public int $estimatedDays = 2,
        public array $rawRequest = [],
        public array $rawResponse = [],
        public ?string $errorMessage = null,
    ) {}
}

