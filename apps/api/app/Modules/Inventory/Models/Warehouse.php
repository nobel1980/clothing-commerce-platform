<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Inventory\Support\InventoryWriteGuard;
use Database\Factories\Inventory\WarehouseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory(): WarehouseFactory
    {
        return WarehouseFactory::new();
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
        'name',
        'code',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country',
        'phone',
        'email',
        'is_active',
        'is_default',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'is_default' => 'boolean',
            'priority'   => 'integer',
        ];
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function reservationItems(): HasMany
    {
        return $this->hasMany(StockReservationItem::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrderedByAllocationPriority($query)
    {
        return $query->orderByDesc('is_default')
            ->orderBy('priority')
            ->orderBy('id');
    }
}

