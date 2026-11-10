<?php

namespace App\Shared\Contracts\Payments;

final readonly class PaymentRefundRequest
{
    public function __construct(
        public int $orderId,
        public string $orderNumber,
        public string $merchantReference,
        public string $gatewayPaymentId,
        public string $gatewayTransactionId,
        public int $refundAmountPaisa,
        public string $currency,
        public string $reason,
        public string $sku = 'ORDER-REFUND',
    ) {}

    public function refundAmountDecimalString(): string
    {
        $major = intdiv($this->refundAmountPaisa, 100);
        $minor = abs($this->refundAmountPaisa % 100);

        return sprintf('%d.%02d', $major, $minor);
    }
}

