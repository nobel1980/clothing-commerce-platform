<?php

namespace App\Shared\Exceptions\Payments;

class WebhookSignatureException extends PaymentException
{
    public function __construct(string $message = 'Invalid payment webhook signature.', array $context = [])
    {
        parent::__construct($message, 400, 'signature', $context);
    }
}

