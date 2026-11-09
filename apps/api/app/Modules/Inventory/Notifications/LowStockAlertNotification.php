<?php

namespace App\Modules\Inventory\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockAlertNotification extends Notification
{
    use Queueable;

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

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("[Low Stock Alert] SKU {$this->sku} at {$this->warehouseCode}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Inventory for SKU **{$this->sku}** at warehouse **{$this->warehouseCode}** has reached or fallen below the low-stock threshold.")
            ->line("Available Stock: **{$this->availableQuantity}** (On Hand: {$this->quantityOnHand}, Reserved: {$this->quantityReserved})")
            ->line("Low-Stock Threshold: **{$this->lowStockThreshold}**")
            ->line('Please review and restock this variant as soon as possible.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'inventory_item_id'   => $this->inventoryItemId,
            'product_variant_id'  => $this->productVariantId,
            'sku'                 => $this->sku,
            'warehouse_id'        => $this->warehouseId,
            'warehouse_code'      => $this->warehouseCode,
            'quantity_on_hand'    => $this->quantityOnHand,
            'quantity_reserved'   => $this->quantityReserved,
            'available_quantity'  => $this->availableQuantity,
            'low_stock_threshold' => $this->lowStockThreshold,
        ];
    }
}

