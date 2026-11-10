<?php

namespace App\Shared\Exceptions\Shipping;

class DuplicateShipmentException extends ShippingException
{
    public function __construct(string $message, array $context = [])
    {
        parent::__construct($message, 409, 'shipment', $context);
    }
}

