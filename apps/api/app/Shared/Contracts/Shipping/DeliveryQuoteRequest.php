<?php

namespace App\Shared\Contracts\Shipping;

final readonly class DeliveryQuoteRequest
{
    public function __construct(
        public string $recipientDivision,
        public string $recipientDistrict,
        public ?string $recipientUpazila = null,
        public int $weightGrams = 500,
        public int $codAmountPaisa = 0,
        public string $deliveryType = 'standard', // 'standard' | 'express'
        public ?int $recipientCityId = null,
        public ?int $recipientZoneId = null,
    ) {}

    public function weightKgDecimal(): float
    {
        return max(0.5, round($this->weightGrams / 1000, 2));
    }
}

