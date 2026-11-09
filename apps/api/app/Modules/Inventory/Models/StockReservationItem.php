<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Inventory\Support\InventoryWriteGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockReservationItem extends Model
{
    protected static function booted(): void
    {
        static::saving(function () {
            InventoryWriteGuard::assertModuleIsolation();
        });

        static::deleting(function () {
            InventoryWriteGuard::assertModuleIsolation();
        });
    }

    protected $fillable = [
        'stock_reservation_id',
        'inventory_item_id',
        'product_variant_id',
        'warehouse_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'stock_reservation_id' => 'integer',
            'inventory_item_id'    => 'integer',
            'product_variant_id'   => 'integer',
            'warehouse_id'         => 'integer',
            'quantity'             => 'integer',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(StockReservation::class, 'stock_reservation_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}

