<?php

namespace App\Shared\Exceptions\Shipping;

class CourierWebhookSignatureException extends ShippingException
{
    public function __construct(string $message, array $context = [])
    {
        parent::__construct($message, 400, 'webhook_signature', $context);
    }
}

