<?php

namespace App\Shared\Exceptions\Inventory;

class ImmutableMovementException extends InventoryException
{
    public function __construct(string $message = 'Inventory movement records are immutable and cannot be updated or deleted.')
    {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return 403;
    }
}

