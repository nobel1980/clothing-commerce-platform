<?php

namespace App\Shared\Exceptions\Checkout;

class InvalidVariantException extends CheckoutException
{
    public function __construct(string $message = 'The selected product variant is unavailable or inactive.', array $context = [])
    {
        parent::__construct($message, 422, 'variant_id', $context);
    }
}

