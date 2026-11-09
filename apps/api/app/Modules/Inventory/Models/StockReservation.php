<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Inventory\Support\InventoryWriteGuard;
use Database\Factories\Inventory\StockReservationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockReservation extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE    = 'active';
    public const STATUS_COMMITTED = 'committed';
    public const STATUS_RELEASED  = 'released';
    public const STATUS_EXPIRED   = 'expired';

    protected static function newFactory(): StockReservationFactory
    {
        return StockReservationFactory::new();
    }

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
        'idempotency_key',
        'reference_type',
        'reference_id',
        'status',
        'expires_at',
        'committed_at',
        'released_at',
        'expired_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'expires_at'   => 'datetime',
            'committed_at' => 'datetime',
            'released_at'  => 'datetime',
            'expired_at'   => 'datetime',
            'metadata'     => 'array',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockReservationItem::class, 'stock_reservation_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isCommitted(): bool
    {
        return $this->status === self::STATUS_COMMITTED;
    }

    public function isReleased(): bool
    {
        return $this->status === self::STATUS_RELEASED;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    public function hasPastExpiry(): bool
    {
        return $this->expires_at !== null && $this->expires_at->lte(now());
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('expires_at', '<=', now());
    }
}

