<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Inventory\Support\InventoryWriteGuard;
use App\Shared\Exceptions\Inventory\NegativeStockException;
use App\Shared\Exceptions\Inventory\UnauthorizedInventoryMutationException;
use Database\Factories\Inventory\InventoryItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    use HasFactory;

    protected static function newFactory(): InventoryItemFactory
    {
        return InventoryItemFactory::new();
    }

    protected static function booted(): void
    {
        static::saving(function (InventoryItem $item) {
            InventoryWriteGuard::assertModuleIsolation();

            $onHand   = (int) ($item->quantity_on_hand ?? 0);
            $reserved = (int) ($item->quantity_reserved ?? 0);

            if ($onHand < 0) {
                throw new NegativeStockException(
                    "quantity_on_hand cannot be negative (attempted: {$onHand})."
                );
            }

            if ($reserved < 0) {
                throw new NegativeStockException(
                    "quantity_reserved cannot be negative (attempted: {$reserved})."
                );
            }

            if (($onHand - $reserved) < 0) {
                throw new NegativeStockException(
                    "Available stock (quantity_on_hand - quantity_reserved) cannot be negative (on_hand: {$onHand}, reserved: {$reserved})."
                );
            }
        });

        static::updating(function (InventoryItem $item) {
            if (
                $item->isDirty(['quantity_on_hand', 'quantity_reserved']) &&
                !InventoryWriteGuard::isStockMutationAuthorized()
            ) {
                throw new UnauthorizedInventoryMutationException(
                    'Direct updates to quantity_on_hand or quantity_reserved are forbidden. All stock modifications must go through Inventory services and record an InventoryMovement.'
                );
            }
        });

        static::deleting(function () {
            InventoryWriteGuard::assertModuleIsolation();
        });
    }

    protected $fillable = [
        'product_variant_id',
        'warehouse_id',
        'quantity_on_hand',
        'quantity_reserved',
        'low_stock_threshold',
        'reorder_point',
        'reorder_quantity',
        'bin_location',
        'low_stock_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'product_variant_id'    => 'integer',
            'warehouse_id'          => 'integer',
            'quantity_on_hand'      => 'integer',
            'quantity_reserved'     => 'integer',
            'low_stock_threshold'   => 'integer',
            'reorder_point'         => 'integer',
            'reorder_quantity'      => 'integer',
            'low_stock_notified_at' => 'datetime',
        ];
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'inventory_item_id');
    }

    public function reservationItems(): HasMany
    {
        return $this->hasMany(StockReservationItem::class, 'inventory_item_id');
    }

    /**
     * Rule: Available stock = quantity_on_hand - quantity_reserved
     */
    public function getAvailableQuantityAttribute(): int
    {
        return (int) $this->quantity_on_hand - (int) $this->quantity_reserved;
    }

    public function availableStock(): int
    {
        return $this->available_quantity;
    }

    public function isLowStock(): bool
    {
        return $this->available_quantity <= (int) $this->low_stock_threshold;
    }

    public function isOutOfStock(): bool
    {
        return $this->available_quantity <= 0;
    }
}

