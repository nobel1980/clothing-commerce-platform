<?php

namespace App\Shared\Exceptions\Inventory;

class WarehouseInactiveException extends InventoryException
{
    public function __construct(int $warehouseId, string $message = '')
    {
        parent::__construct($message ?: "Warehouse #{$warehouseId} is inactive and cannot fulfill stock operations.");
    }
}

