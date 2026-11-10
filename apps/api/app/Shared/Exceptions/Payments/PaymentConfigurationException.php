<?php

namespace App\Shared\Exceptions\Payments;

class PaymentConfigurationException extends PaymentException
{
    public function __construct(string $message, array $context = [])
    {
        parent::__construct($message, 500, 'gateway', $context);
    }
}

