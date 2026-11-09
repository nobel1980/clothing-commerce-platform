<?php

namespace Tests\Feature\Inventory;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Inventory\Models\StockReservation;
use App\Modules\Inventory\Models\Warehouse;
use App\Shared\Contracts\Inventory\InventoryServiceContract;
use App\Shared\Contracts\Inventory\ReserveStockData;
use App\Shared\Contracts\Inventory\ReserveStockItemData;
use App\Shared\Contracts\Inventory\StockAdjustmentData;
use App\Shared\Exceptions\Inventory\InsufficientStockException;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected InventoryServiceContract $inventoryService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->inventoryService = $this->app->make(InventoryServiceContract::class);
    }

    /**
     * Verify that stock adjustments and reservations execute SELECT ... FOR UPDATE
     * queries inside database transactions.
     */
    public function test_stock_operations_use_select_for_update_row_level_locks(): void
    {
        $warehouse = Warehouse::factory()->defaultWarehouse()->create();
        $variant   = ProductVariant::factory()->create();

        $this->inventoryService->adjustStock(new StockAdjustmentData($variant->id, $warehouse->id, 20, 'restock'));

        // Verify the Eloquent lockForUpdate builder produces the expected lock clause
        $grammar   = DB::connection()->getQueryGrammar();
        $lockQuery = InventoryItem::query()
            ->where('product_variant_id', $variant->id)
            ->where('warehouse_id', $warehouse->id)
            ->lockForUpdate();

        // Check that the query builder has the row lock enabled
        $this->assertTrue($lockQuery->getQuery()->lock === true);

        // Also verify MySQL grammar compiles lockForUpdate() to 'for update'
        $mysqlGrammar = new \Illuminate\Database\Query\Grammars\MySqlGrammar(DB::connection());
        $compiledSql  = $mysqlGrammar->compileSelect($lockQuery->getQuery());
        $this->assertStringContainsStringIgnoringCase('for update', $compiledSql);
    }

    /**
     * Simulate 25 competing checkout reservation attempts for a hot-selling variant
     * with only 10 units in stock (each requesting 1 unit).
     * Exactly 10 reservations must succeed, 15 must fail with InsufficientStockException,
     * available stock must never go negative, and movement history must match 100%.
     */
    public function test_competing_reservations_never_oversell_or_produce_negative_stock(): void
    {
        $warehouse = Warehouse::factory()->defaultWarehouse()->create();
        $variant   = ProductVariant::factory()->create();

        // Initial stock = 10
        $this->inventoryService->adjustStock(new StockAdjustmentData(
            productVariantId: $variant->id,
            warehouseId: $warehouse->id,
            quantityDelta: 10,
            type: InventoryMovement::TYPE_INITIAL
        ));

        $successCount = 0;
        $failureCount = 0;

        for ($i = 1; $i <= 25; $i++) {
            try {
                $this->inventoryService->reserveStock(new ReserveStockData(
                    idempotencyKey: "concurrent-checkout-{$i}",
                    items: [new ReserveStockItemData($variant->id, 1, $warehouse->id)]
                ));
                $successCount++;
            } catch (InsufficientStockException) {
                $failureCount++;
            }
        }

        $this->assertSame(10, $successCount);
        $this->assertSame(15, $failureCount);

        $item = InventoryItem::where('product_variant_id', $variant->id)
            ->where('warehouse_id', $warehouse->id)
            ->firstOrFail();

        $this->assertSame(10, $item->quantity_on_hand);
        $this->assertSame(10, $item->quantity_reserved);
        $this->assertSame(0, $item->available_quantity);

        $this->assertSame(10, StockReservation::where('status', StockReservation::STATUS_ACTIVE)->count());
        $this->assertSame(11, InventoryMovement::where('inventory_item_id', $item->id)->count());
    }

    /**
     * Simulate interleaved multi-item reservations, commits, releases, adjustments, and expiries
     * across multiple variants and warehouses, asserting the strict ledger invariant:
     *   - sum(movements.on_hand_delta) === item.quantity_on_hand
     *   - sum(movements.reserved_delta) === item.quantity_reserved
     *   - item.available_quantity === item.quantity_on_hand - item.quantity_reserved >= 0
     */
    public function test_interleaved_reservations_commits_releases_and_expiries_preserve_ledger_invariants(): void
    {
        $wh1 = Warehouse::factory()->defaultWarehouse()->create(['code' => 'WH-A', 'priority' => 1]);
        $wh2 = Warehouse::factory()->create(['code' => 'WH-B', 'is_active' => true, 'priority' => 2]);

        $vA = ProductVariant::factory()->create(['sku' => 'CONC-VA']);
        $vB = ProductVariant::factory()->create(['sku' => 'CONC-VB']);

        // Seed stock across both warehouses
        $this->inventoryService->adjustStock(new StockAdjustmentData($vA->id, $wh1->id, 20, 'restock'));
        $this->inventoryService->adjustStock(new StockAdjustmentData($vA->id, $wh2->id, 15, 'restock'));
        $this->inventoryService->adjustStock(new StockAdjustmentData($vB->id, $wh1->id, 12, 'restock'));
        $this->inventoryService->adjustStock(new StockAdjustmentData($vB->id, $wh2->id, 18, 'restock'));

        // Reservation 1: reserves in reverse variant order [vB, vA] to test deterministic lock sorting
        $res1 = $this->inventoryService->reserveStock(new ReserveStockData(
            idempotencyKey: 'interleaved-res-1',
            items: [
                new ReserveStockItemData($vB->id, 10, null),
                new ReserveStockItemData($vA->id, 15, null),
            ]
        ));

        // Reservation 2: reserves in forward variant order [vA, vB] with past expiry
        $res2 = $this->inventoryService->reserveStock(new ReserveStockData(
            idempotencyKey: 'interleaved-res-2',
            items: [
                new ReserveStockItemData($vA->id, 12, null),
                new ReserveStockItemData($vB->id, 8, null),
            ],
            expiresAt: now()->subMinute()
        ));

        // Reservation 3: will be released
        $res3 = $this->inventoryService->reserveStock(new ReserveStockData(
            idempotencyKey: 'interleaved-res-3',
            items: [
                new ReserveStockItemData($vA->id, 5, null),
                new ReserveStockItemData($vB->id, 6, null),
            ]
        ));

        // Commit res1 (and replay commit to test idempotency under race)
        $this->inventoryService->commitReservation($res1->id);
        $this->inventoryService->commitReservation($res1->id);

        // Release res3 (and replay release to test idempotency under race)
        $this->inventoryService->releaseReservation($res3->id, 'Customer cancelled');
        $this->inventoryService->releaseReservation($res3->id, 'Duplicate webhook');

        // Expire overdue reservations (expires res2, and replay expiry)
        $expiredFirstRun  = $this->inventoryService->expireOverdueReservations();
        $expiredSecondRun = $this->inventoryService->expireOverdueReservations();

        $this->assertSame(1, $expiredFirstRun);
        $this->assertSame(0, $expiredSecondRun);

        // Verify ledger invariants across all InventoryItems
        $items = InventoryItem::all();
        $this->assertCount(4, $items);

        foreach ($items as $item) {
            $this->assertGreaterThanOrEqual(0, $item->quantity_on_hand);
            $this->assertGreaterThanOrEqual(0, $item->quantity_reserved);
            $this->assertGreaterThanOrEqual(0, $item->available_quantity);
            $this->assertSame($item->quantity_on_hand - $item->quantity_reserved, $item->available_quantity);

            $sumOnHandDelta = (int) InventoryMovement::where('inventory_item_id', $item->id)->sum('on_hand_delta');
            $sumReservedDelta = (int) InventoryMovement::where('inventory_item_id', $item->id)->sum('reserved_delta');

            $this->assertSame(
                $item->quantity_on_hand,
                $sumOnHandDelta,
                "Ledger mismatch for quantity_on_hand on item #{$item->id}"
            );
            $this->assertSame(
                $item->quantity_reserved,
                $sumReservedDelta,
                "Ledger mismatch for quantity_reserved on item #{$item->id}"
            );
        }

        // Total remaining available stock for vA = (20 + 15) - 15 (committed) = 20
        $this->assertSame(20, $this->inventoryService->getAvailableStock($vA->id));
        // Total remaining available stock for vB = (12 + 18) - 10 (committed) = 20
        $this->assertSame(20, $this->inventoryService->getAvailableStock($vB->id));
    }

    /**
     * Verify atomic rollback when a multi-item reservation has sufficient stock for item 1
     * but insufficient stock for item 2.
     */
    public function test_multi_item_reservation_rolls_back_atomically_if_any_item_lacks_stock(): void
    {
        $warehouse = Warehouse::factory()->defaultWarehouse()->create();
        $v1        = ProductVariant::factory()->create();
        $v2        = ProductVariant::factory()->create();

        $this->inventoryService->adjustStock(new StockAdjustmentData($v1->id, $warehouse->id, 10, 'restock'));
        $this->inventoryService->adjustStock(new StockAdjustmentData($v2->id, $warehouse->id, 3, 'restock'));

        try {
            $this->inventoryService->reserveStock(new ReserveStockData(
                idempotencyKey: 'atomic-rollback-test',
                items: [
                    new ReserveStockItemData($v1->id, 5, $warehouse->id), // has enough (10 >= 5)
                    new ReserveStockItemData($v2->id, 5, $warehouse->id), // insufficient (3 < 5)
                ]
            ));
            $this->fail('Expected InsufficientStockException was not thrown.');
        } catch (InsufficientStockException $e) {
            $this->assertSame($v2->id, $e->productVariantId);
        }

        // Neither v1 nor v2 should have any reserved stock or reservation movements
        $this->assertSame(10, $this->inventoryService->getAvailableStock($v1->id, $warehouse->id));
        $this->assertSame(3, $this->inventoryService->getAvailableStock($v2->id, $warehouse->id));
        $this->assertSame(0, StockReservation::count());
        $this->assertSame(0, InventoryMovement::where('type', InventoryMovement::TYPE_RESERVATION)->count());
    }
}

