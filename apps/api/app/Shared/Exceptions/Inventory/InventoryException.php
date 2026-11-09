<?php

namespace App\Shared\Exceptions\Inventory;

use RuntimeException;

class InventoryException extends RuntimeException
{
    public function statusCode(): int
    {
        return 422;
    }
}

