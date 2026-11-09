<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Support\InventoryWriteGuard;
use App\Shared\Exceptions\Inventory\ImmutableMovementException;
use App\Shared\Exceptions\Inventory\UnauthorizedInventoryMutationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    public const TYPE_INITIAL             = 'initial';
    public const TYPE_RESTOCK             = 'restock';
    public const TYPE_ADJUSTMENT          = 'adjustment';
    public const TYPE_SALE                = 'sale';
    public const TYPE_RETURN              = 'return';
    public const TYPE_DAMAGE              = 'damage';
    public const TYPE_WRITE_OFF           = 'write_off';
    public const TYPE_TRANSFER_OUT        = 'transfer_out';
    public const TYPE_TRANSFER_IN         = 'transfer_in';
    public const TYPE_RESERVATION         = 'reservation';
    public const TYPE_RESERVATION_RELEASE = 'reservation_release';
    public const TYPE_RESERVATION_EXPIRED = 'reservation_expired';
    public const TYPE_RESERVATION_COMMIT  = 'reservation_commit';

    public const ALLOWED_TYPES = [
        self::TYPE_INITIAL,
        self::TYPE_RESTOCK,
        self::TYPE_ADJUSTMENT,
        self::TYPE_SALE,
        self::TYPE_RETURN,
        self::TYPE_DAMAGE,
        self::TYPE_WRITE_OFF,
        self::TYPE_TRANSFER_OUT,
        self::TYPE_TRANSFER_IN,
        self::TYPE_RESERVATION,
        self::TYPE_RESERVATION_RELEASE,
        self::TYPE_RESERVATION_EXPIRED,
        self::TYPE_RESERVATION_COMMIT,
    ];

    protected static function booted(): void
    {
        static::creating(function () {
            InventoryWriteGuard::assertModuleIsolation();

            if (!InventoryWriteGuard::isStockMutationAuthorized()) {
                throw new UnauthorizedInventoryMutationException(
                    'InventoryMovement records can only be created through authorized Inventory module stock operations.'
                );
            }
        });

        static::updating(function () {
            throw new ImmutableMovementException();
        });

        static::deleting(function () {
            throw new ImmutableMovementException();
        });
    }

    protected $fillable = [
        'inventory_item_id',
        'product_variant_id',
        'warehouse_id',
        'type',
        'quantity_change',
        'on_hand_delta',
        'reserved_delta',
        'quantity_before',
        'quantity_after',
        'reserved_before',
        'reserved_after',
        'reference_type',
        'reference_id',
        'reason',
        'notes',
        'performed_by',
    ];

    protected function casts(): array
    {
        return [
            'inventory_item_id'  => 'integer',
            'product_variant_id' => 'integer',
            'warehouse_id'       => 'integer',
            'quantity_change'    => 'integer',
            'on_hand_delta'      => 'integer',
            'reserved_delta'     => 'integer',
            'quantity_before'    => 'integer',
            'quantity_after'     => 'integer',
            'reserved_before'    => 'integer',
            'reserved_after'     => 'integer',
            'performed_by'       => 'integer',
        ];
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

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function getAvailableBeforeAttribute(): int
    {
        return (int) $this->quantity_before - (int) $this->reserved_before;
    }

    public function getAvailableAfterAttribute(): int
    {
        return (int) $this->quantity_after - (int) $this->reserved_after;
    }
}

