<?php

namespace App\Shared\Exceptions\Payments;

class DuplicatePaymentException extends PaymentException
{
    public function __construct(string $message = 'This order has already been paid.', array $context = [])
    {
        parent::__construct($message, 409, 'payment', $context);
    }
}

