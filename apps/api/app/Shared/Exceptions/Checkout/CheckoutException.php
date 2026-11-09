<?php

namespace App\Shared\Exceptions\Checkout;

use RuntimeException;

class CheckoutException extends RuntimeException
{
    public function __construct(
        string $message,
        protected int $httpStatus = 422,
        protected string $errorField = 'checkout',
        protected array $context = []
    ) {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->httpStatus;
    }

    public function errorField(): string
    {
        return $this->errorField;
    }

    public function context(): array
    {
        return $this->context;
    }
}

