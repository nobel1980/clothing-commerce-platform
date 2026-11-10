<?php

namespace App\Shared\Exceptions\Orders;

use App\Shared\Exceptions\Checkout\CheckoutException;

class InvalidOrderStateException extends CheckoutException
{
    public function __construct(string $message, array $context = [])
    {
        parent::__construct($message, 422, 'order_status', $context);
    }
}

