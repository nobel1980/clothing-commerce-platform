<?php

namespace App\Modules\Inventory\Jobs;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Notifications\LowStockAlertNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendLowStockNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 3600;

    public function __construct(
        public readonly int $inventoryItemId,
        public readonly int $productVariantId,
        public readonly string $sku,
        public readonly int $warehouseId,
        public readonly string $warehouseCode,
        public readonly int $quantityOnHand,
        public readonly int $quantityReserved,
        public readonly int $availableQuantity,
        public readonly int $lowStockThreshold,
    ) {}

    public function handle(): void
    {
        Log::warning('[Inventory] Low stock threshold reached', [
            'inventory_item_id'   => $this->inventoryItemId,
            'product_variant_id'  => $this->productVariantId,
            'sku'                 => $this->sku,
            'warehouse_id'        => $this->warehouseId,
            'warehouse_code'      => $this->warehouseCode,
            'quantity_on_hand'    => $this->quantityOnHand,
            'quantity_reserved'   => $this->quantityReserved,
            'available_quantity'  => $this->availableQuantity,
            'low_stock_threshold' => $this->lowStockThreshold,
        ]);

        $recipients = User::query()
            ->where('status', 'active')
            ->whereIn('role', ['super_admin', 'inventory_manager', 'product_manager'])
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send(
            $recipients,
            new LowStockAlertNotification(
                inventoryItemId: $this->inventoryItemId,
                productVariantId: $this->productVariantId,
                sku: $this->sku,
                warehouseId: $this->warehouseId,
                warehouseCode: $this->warehouseCode,
                quantityOnHand: $this->quantityOnHand,
                quantityReserved: $this->quantityReserved,
                availableQuantity: $this->availableQuantity,
                lowStockThreshold: $this->lowStockThreshold,
            )
        );
    }
}

