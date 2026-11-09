<?php

namespace App\Shared\Exceptions\Inventory;

class NegativeStockException extends InventoryException
{
    public function __construct(string $message = 'Operation would result in negative stock, which is strictly prohibited.')
    {
        parent::__construct($message);
    }
}

