<?php

namespace App\Shared\Exceptions\Inventory;

class InvalidReservationStateException extends InventoryException
{
    public function __construct(string $message = 'Stock reservation is in an invalid state for this operation.')
    {
        parent::__construct($message);
    }
}

