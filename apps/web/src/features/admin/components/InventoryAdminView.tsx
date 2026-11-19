'use client';

import * as React from 'react';
import type {
  CatalogVariant,
  InventoryItem,
  InventoryMovement,
  PaginationMeta,
  Warehouse,
} from '@clothing-commerce/types';
import {
  adjustAdminStock,
  downloadAdminCsvExport,
  fetchAdminInventoryItems,
  fetchAdminInventoryMovements,
  fetchAdminWarehouses,
} from '@/features/admin/api/adminApi';
import { useAdminAuth } from '@/features/admin/hooks/useAdminAuth';
import {
  AdminDataToolbar,
  AdminPaginationBar,
  ConfirmDialog,
} from '@/features/admin/components/AdminDataToolbar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type EnrichedInventoryItem = InventoryItem & { product_variant?: CatalogVariant };

export function InventoryAdminView({
  initialItems = [],
  initialWarehouses = [],
}: {
  initialItems?: EnrichedInventoryItem[];
  initialWarehouses?: Warehouse[];
}) {
  const { hasPermission } = useAdminAuth();
  const canManage = hasPermission('inventory.manage');

  const [items, setItems] = React.useState<EnrichedInventoryItem[]>(initialItems);
  const [warehouses, setWarehouses] = React.useState<Warehouse[]>(initialWarehouses);
  const [movements, setMovements] = React.useState<InventoryMovement[]>([]);
  const [search, setSearch] = React.useState('');
  const [warehouseFilter, setWarehouseFilter] = React.useState('');
  const [stockFilter, setStockFilter] = React.useState('');
  const [page, setPage] = React.useState(1);
  const [meta, setMeta] = React.useState<PaginationMeta>({
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: initialItems.length,
  });

  const [adjustTarget, setAdjustTarget] = React.useState<EnrichedInventoryItem | null>(
    null
  );
  const [adjustDelta, setAdjustDelta] = React.useState('10');

  const loadData = React.useCallback(async () => {
    try {
      const [itemsRes, whRes, movRes] = await Promise.all([
        fetchAdminInventoryItems({
          search: search || undefined,
          warehouse_id: warehouseFilter ? Number(warehouseFilter) : undefined,
          low_stock: stockFilter === 'low_stock' ? true : undefined,
          out_of_stock: stockFilter === 'out_of_stock' ? true : undefined,
          page,
        }),
        warehouses.length === 0 ? fetchAdminWarehouses() : Promise.resolve({ data: warehouses }),
        fetchAdminInventoryMovements({ per_page: 10 }),
      ]);
      setItems(itemsRes.data);
      if (itemsRes.meta) {
        setMeta(itemsRes.meta);
      }
      if (warehouses.length === 0) {
        setWarehouses(whRes.data);
      }
      setMovements(movRes.data);
    } catch {
      // Handled gracefully
    }
  }, [search, warehouseFilter, stockFilter, page, warehouses]);

  React.useEffect(() => {
    if (initialItems.length === 0) {
      void loadData();
    }
  }, [initialItems.length, loadData]);

  const handleConfirmAdjustment = async (reason?: string) => {
    if (!adjustTarget) return;
    const delta = Number(adjustDelta);
    if (!delta || Number.isNaN(delta)) return;

    await adjustAdminStock({
      product_variant_id: adjustTarget.product_variant_id,
      warehouse_id: adjustTarget.warehouse_id,
      quantity_delta: Math.round(delta),
      type: delta > 0 ? 'restock' : 'adjustment',
      reason: reason || 'Admin inventory adjustment',
    });

    setAdjustTarget(null);
    await loadData();
  };

  return (
    <div className="space-y-6">
      <AdminDataToolbar
        searchPlaceholder="Search inventory by SKU or product name..."
        searchValue={search}
        onSearchChange={(val) => {
          setSearch(val);
          setPage(1);
        }}
        filters={[
          {
            key: 'warehouse',
            label: 'Warehouse',
            value: warehouseFilter,
            options: warehouses.map((w) => ({
              label: `${w.name} (${w.code})`,
              value: String(w.id),
            })),
            onChange: setWarehouseFilter,
          },
          {
            key: 'stock_health',
            label: 'Stock Health',
            value: stockFilter,
            options: [
              { label: 'Low Stock Only', value: 'low_stock' },
              { label: 'Out of Stock Only', value: 'out_of_stock' },
            ],
            onChange: setStockFilter,
          },
        ]}
        totalCount={meta.total}
        onExportCsv={() => void downloadAdminCsvExport('inventory')}
      />

      <div className="overflow-x-auto rounded-xl border border-neutral-200 bg-white shadow-2xs">
        <table className="w-full text-left text-xs">
          <thead>
            <tr className="border-b border-neutral-200 bg-neutral-50 text-[11px] uppercase tracking-wider text-neutral-500">
              <th className="px-4 py-3">SKU / Variant</th>
              <th className="px-4 py-3">Warehouse</th>
              <th className="px-4 py-3 text-right">On Hand</th>
              <th className="px-4 py-3 text-right">Reserved</th>
              <th className="px-4 py-3 text-right">Available</th>
              <th className="px-4 py-3">Status</th>
              <th className="px-4 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-100">
            {items.length === 0 ? (
              <tr>
                <td colSpan={7} className="px-4 py-8 text-center text-neutral-500">
                  No inventory records match the current filters.
                </td>
              </tr>
            ) : (
              items.map((item) => (
                <tr key={item.id}>
                  <td className="px-4 py-3">
                    <div className="font-mono font-semibold text-neutral-900">
                      {item.product_variant?.sku ?? `Variant #${item.product_variant_id}`}
                    </div>
                    <div className="text-[11px] text-neutral-500">
                      Threshold: {item.low_stock_threshold} units
                    </div>
                  </td>
                  <td className="px-4 py-3 text-neutral-700">
                    {item.warehouse?.name ?? `Warehouse #${item.warehouse_id}`}
                  </td>
                  <td className="px-4 py-3 text-right font-medium text-neutral-900">
                    {item.quantity_on_hand}
                  </td>
                  <td className="px-4 py-3 text-right text-neutral-600">
                    {item.quantity_reserved}
                  </td>
                  <td className="px-4 py-3 text-right font-bold text-neutral-900">
                    {item.available_quantity}
                  </td>
                  <td className="px-4 py-3">
                    {item.is_out_of_stock ? (
                      <Badge variant="destructive">Out of Stock</Badge>
                    ) : item.is_low_stock ? (
                      <Badge variant="destructive">Low Stock</Badge>
                    ) : (
                      <Badge variant="secondary">Healthy</Badge>
                    )}
                  </td>
                  <td className="px-4 py-3 text-right">
                    {canManage && (
                      <div className="inline-flex items-center gap-2">
                        <Input
                          type="number"
                          aria-label="Quantity Delta"
                          value={adjustTarget?.id === item.id ? adjustDelta : '10'}
                          onChange={(e) => {
                            setAdjustTarget(item);
                            setAdjustDelta(e.target.value);
                          }}
                          className="h-8 w-20 text-xs"
                        />
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          onClick={() => setAdjustTarget(item)}
                        >
                          Adjust Stock
                        </Button>
                      </div>
                    )}
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      <AdminPaginationBar
        currentPage={meta.current_page}
        lastPage={meta.last_page}
        total={meta.total}
        onPageChange={setPage}
      />

      {/* Recent Stock Movements Ledger */}
      {movements.length > 0 && (
        <section className="rounded-xl border border-neutral-200 bg-white p-5 shadow-2xs">
          <h3 className="text-sm font-bold text-neutral-900">
            Recent Inventory Movement Ledger
          </h3>
          <div className="mt-3 overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead>
                <tr className="border-b border-neutral-200 text-[11px] uppercase tracking-wider text-neutral-500">
                  <th className="py-2 pr-3">Movement Type</th>
                  <th className="px-3 py-2 text-right">Delta</th>
                  <th className="px-3 py-2 text-right">Before → After</th>
                  <th className="pl-3 py-2">Reason</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {movements.map((mov) => (
                  <tr key={mov.id}>
                    <td className="py-2 pr-3 font-medium text-neutral-900">
                      <Badge variant="outline">{mov.type}</Badge>
                    </td>
                    <td className="px-3 py-2 text-right font-mono font-semibold">
                      {mov.on_hand_delta > 0 ? `+${mov.on_hand_delta}` : mov.on_hand_delta}
                    </td>
                    <td className="px-3 py-2 text-right text-neutral-600">
                      {mov.quantity_before} → {mov.quantity_after}
                    </td>
                    <td className="pl-3 py-2 text-neutral-500">
                      {mov.reason || mov.reference_type || 'System ledger entry'}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      )}

      <ConfirmDialog
        open={adjustTarget !== null}
        title={`Confirm Stock Adjustment (${Number(adjustDelta) > 0 ? `+${adjustDelta}` : adjustDelta} units)`}
        description="This stock movement will update the warehouse balance under row lock and write an immutable InventoryMovement ledger entry."
        confirmLabel="Commit Adjustment"
        requireReason={true}
        reasonLabel="Adjustment Reason"
        reasonPlaceholder="e.g. Cycle count restock / Damaged item write-off"
        onConfirm={(reason) => void handleConfirmAdjustment(reason)}
        onCancel={() => setAdjustTarget(null)}
      />
    </div>
  );
}

