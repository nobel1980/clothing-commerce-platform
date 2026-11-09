<?php

namespace App\Shared\Exceptions\Checkout;

class EmptyCartException extends CheckoutException
{
    public function __construct(string $message = 'Your cart is empty.')
    {
        parent::__construct($message, 422, 'items');
    }
}

