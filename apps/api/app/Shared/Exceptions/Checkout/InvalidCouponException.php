<?php

namespace App\Shared\Exceptions\Checkout;

class InvalidCouponException extends CheckoutException
{
    public function __construct(string $message, array $context = [])
    {
        parent::__construct($message, 422, 'coupon_code', $context);
    }
}

