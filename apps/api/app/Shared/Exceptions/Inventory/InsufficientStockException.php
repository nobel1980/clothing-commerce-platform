<?php

namespace App\Shared\Exceptions\Inventory;

class InsufficientStockException extends NegativeStockException
{
    public function __construct(
        public readonly int $productVariantId,
        public readonly int $requestedQuantity,
        public readonly int $availableQuantity,
        public readonly ?int $warehouseId = null,
        string $message = ''
    ) {
        if ($message === '') {
            $wh = $warehouseId ? " in warehouse #{$warehouseId}" : '';
            $message = "Insufficient stock for variant #{$productVariantId}{$wh}: requested {$requestedQuantity}, available {$availableQuantity}.";
        }

        parent::__construct($message);
    }
}
