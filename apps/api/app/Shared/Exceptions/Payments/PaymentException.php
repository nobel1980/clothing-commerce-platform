<?php

namespace App\Shared\Exceptions\Payments;

use App\Shared\Exceptions\Checkout\CheckoutException;

class PaymentException extends CheckoutException
{
    public function __construct(
        string $message,
        int $httpStatus = 422,
        string $errorField = 'payment',
        array $context = []
    ) {
        parent::__construct($message, $httpStatus, $errorField, $context);
    }
}

