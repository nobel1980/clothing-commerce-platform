<?php

namespace App\Shared\Exceptions\Shipping;

class InvalidShipmentStateException extends ShippingException
{
    public function __construct(string $message, array $context = [])
    {
        parent::__construct($message, 422, 'shipment_status', $context);
    }
}

