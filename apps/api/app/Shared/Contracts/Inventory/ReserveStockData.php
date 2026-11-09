<?php

namespace App\Shared\Contracts\Inventory;

use Carbon\CarbonInterface;

final readonly class ReserveStockData
{
    /**
     * @param array<int, ReserveStockItemData|array{product_variant_id: int, quantity: int, warehouse_id?: ?int}> $items
     */
    public function __construct(
        public string $idempotencyKey,
        public array $items,
        public int $ttlMinutes = 15,
        public ?CarbonInterface $expiresAt = null,
        public ?string $referenceType = null,
        public ?string $referenceId = null,
        public ?array $metadata = null,
        public ?int $performedBy = null,
    ) {}
}

