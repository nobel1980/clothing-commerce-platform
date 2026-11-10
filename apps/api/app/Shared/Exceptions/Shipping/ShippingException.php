<?php

namespace App\Shared\Exceptions\Shipping;

use App\Shared\Exceptions\Checkout\CheckoutException;

class ShippingException extends CheckoutException
{
    public function __construct(
        string $message,
        int $httpStatus = 422,
        string $errorKey = 'shipping',
        array $context = []
    ) {
        parent::__construct($message, $httpStatus, $errorKey, $context);
    }
}

