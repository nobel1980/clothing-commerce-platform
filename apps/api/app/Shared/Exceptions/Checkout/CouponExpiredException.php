<?php

namespace App\Shared\Exceptions\Checkout;

class CouponExpiredException extends CheckoutException
{
    public function __construct(string $code, string $message = '')
    {
        $msg = $message !== '' ? $message : "Coupon code '{$code}' has expired.";
        parent::__construct($msg, 422, 'coupon_code', ['coupon_code' => $code]);
    }
}

