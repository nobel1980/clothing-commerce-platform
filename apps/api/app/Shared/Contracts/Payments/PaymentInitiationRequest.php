<?php

namespace App\Shared\Contracts\Payments;

final readonly class PaymentInitiationRequest
{
    public function __construct(
        public int $orderId,
        public string $orderNumber,
        public string $merchantReference,
        public int $amountPaisa,
        public string $currency,
        public string $customerName,
        public string $customerPhone,
        public ?string $customerEmail = null,
        public string $customerAddress = 'Dhaka, Bangladesh',
        public string $customerCity = 'Dhaka',
        public string $customerPostcode = '1212',
        public ?string $callbackUrl = null,
        public array $metadata = [],
    ) {}

    public function amountDecimalString(): string
    {
        $major = intdiv($this->amountPaisa, 100);
        $minor = abs($this->amountPaisa % 100);

        return sprintf('%d.%02d', $major, $minor);
    }
}

