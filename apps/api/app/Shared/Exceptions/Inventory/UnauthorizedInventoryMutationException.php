<?php

namespace App\Shared\Exceptions\Inventory;

class UnauthorizedInventoryMutationException extends InventoryException
{
    public function __construct(string $message = 'Direct mutation of inventory tables outside the Inventory module is forbidden.')
    {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return 403;
    }
}

