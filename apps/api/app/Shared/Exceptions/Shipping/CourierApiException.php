<?php

namespace App\Shared\Exceptions\Shipping;

class CourierApiException extends ShippingException
{
    public function __construct(string $message, array $context = [])
    {
        parent::__construct($message, 502, 'courier', $context);
    }
}

