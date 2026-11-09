<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Support\InventoryWriteGuard;
use App\Shared\Exceptions\Inventory\InventoryException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class WarehouseService
{
    public function list(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Warehouse::query()->withCount('inventoryItems');

        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                    ->orWhere('code', 'like', $search)
                    ->orWhere('city', 'like', $search);
            });
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->orderedByAllocationPriority()->paginate($perPage);
    }

    public function getActiveWarehouses(): Collection
    {
        return Warehouse::query()
            ->active()
            ->orderedByAllocationPriority()
            ->get();
    }

    public function create(array $data): Warehouse
    {
        return DB::transaction(function () use ($data) {
            return InventoryWriteGuard::authorizeStockMutation(function () use ($data) {
                $isDefault = (bool) ($data['is_default'] ?? false);

                if ($isDefault) {
                    Warehouse::query()->where('is_default', true)->update(['is_default' => false]);
                } elseif (!Warehouse::query()->where('is_default', true)->exists()) {
                    $isDefault = true;
                }

                return Warehouse::create([
                    'name'           => $data['name'],
                    'code'           => strtoupper(trim($data['code'])),
                    'address_line_1' => $data['address_line_1'] ?? null,
                    'address_line_2' => $data['address_line_2'] ?? null,
                    'city'           => $data['city'] ?? null,
                    'state'          => $data['state'] ?? null,
                    'postal_code'    => $data['postal_code'] ?? null,
                    'country'        => strtoupper($data['country'] ?? 'BD'),
                    'phone'          => $data['phone'] ?? null,
                    'email'          => $data['email'] ?? null,
                    'is_active'      => $data['is_active'] ?? true,
                    'is_default'     => $isDefault,
                    'priority'       => (int) ($data['priority'] ?? 10),
                ]);
            });
        });
    }

    public function update(Warehouse $warehouse, array $data): Warehouse
    {
        return DB::transaction(function () use ($warehouse, $data) {
            return InventoryWriteGuard::authorizeStockMutation(function () use ($warehouse, $data) {
                $locked = Warehouse::query()->whereKey($warehouse->id)->lockForUpdate()->firstOrFail();

                if (!empty($data['is_default'])) {
                    Warehouse::query()
                        ->where('id', '!=', $locked->id)
                        ->where('is_default', true)
                        ->update(['is_default' => false]);
                }

                $locked->update([
                    'name'           => $data['name'] ?? $locked->name,
                    'code'           => isset($data['code']) ? strtoupper(trim($data['code'])) : $locked->code,
                    'address_line_1' => array_key_exists('address_line_1', $data) ? $data['address_line_1'] : $locked->address_line_1,
                    'address_line_2' => array_key_exists('address_line_2', $data) ? $data['address_line_2'] : $locked->address_line_2,
                    'city'           => array_key_exists('city', $data) ? $data['city'] : $locked->city,
                    'state'          => array_key_exists('state', $data) ? $data['state'] : $locked->state,
                    'postal_code'    => array_key_exists('postal_code', $data) ? $data['postal_code'] : $locked->postal_code,
                    'country'        => isset($data['country']) ? strtoupper($data['country']) : $locked->country,
                    'phone'          => array_key_exists('phone', $data) ? $data['phone'] : $locked->phone,
                    'email'          => array_key_exists('email', $data) ? $data['email'] : $locked->email,
                    'is_active'      => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $locked->is_active,
                    'is_default'     => array_key_exists('is_default', $data) ? (bool) $data['is_default'] : $locked->is_default,
                    'priority'       => array_key_exists('priority', $data) ? (int) $data['priority'] : $locked->priority,
                ]);

                return $locked->fresh();
            });
        });
    }

    public function delete(Warehouse $warehouse): void
    {
        DB::transaction(function () use ($warehouse) {
            InventoryWriteGuard::authorizeStockMutation(function () use ($warehouse) {
                $locked = Warehouse::query()->whereKey($warehouse->id)->lockForUpdate()->firstOrFail();

                $hasStockOrReservations = $locked->inventoryItems()
                    ->where(function ($q) {
                        $q->where('quantity_on_hand', '>', 0)
                            ->orWhere('quantity_reserved', '>', 0);
                    })
                    ->exists();

                if ($hasStockOrReservations) {
                    throw new InventoryException(
                        'Cannot delete a warehouse that currently holds on-hand or reserved inventory.'
                    );
                }

                $locked->delete();
            });
        });
    }
}

