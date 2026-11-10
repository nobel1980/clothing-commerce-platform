<?php

namespace App\Shared\Exceptions\Payments;

class PaymentVerificationMismatchException extends PaymentException
{
    public function __construct(string $message, array $context = [])
    {
        parent::__construct($message, 422, 'payment_verification', $context);
    }
}

