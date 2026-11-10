<?php

namespace App\Shared\Contracts\Shipping;

final readonly class CreateShipmentRequest
{
    public function __construct(
        public int $orderId,
        public string $orderNumber,
        public string $merchantOrderReference,
        public string $recipientName,
        public string $recipientPhone,
        public string $recipientAddress,
        public string $recipientDivision,
        public string $recipientDistrict,
        public ?string $recipientUpazila = null,
        public ?string $recipientPostalCode = null,
        public int $codAmountPaisa = 0,
        public int $declaredValuePaisa = 0,
        public int $weightGrams = 500,
        public int $itemQuantity = 1,
        public string $itemDescription = 'Apparel & Garments',
        public ?string $specialInstruction = null,
        public string $deliveryType = 'standard',
        public ?int $recipientCityId = null,
        public ?int $recipientZoneId = null,
        public ?int $recipientAreaId = null,
    ) {}

    /**
     * Convert COD amount in paisa to whole BDT or decimal string as required by courier APIs.
     */
    public function codAmountBdtInt(): int
    {
        return (int) round($this->codAmountPaisa / 100);
    }

    public function weightKg(): float
    {
        return max(0.5, round($this->weightGrams / 1000, 2));
    }
}

