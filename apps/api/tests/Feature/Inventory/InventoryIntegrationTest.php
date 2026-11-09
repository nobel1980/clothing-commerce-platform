<?php

namespace Tests\Feature\Inventory;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Jobs\ExpireStockReservationsJob;
use App\Modules\Inventory\Jobs\SendLowStockNotificationJob;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Inventory\Models\StockReservation;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Notifications\LowStockAlertNotification;
use App\Shared\Contracts\Inventory\InventoryServiceContract;
use App\Shared\Contracts\Inventory\ReserveStockData;
use App\Shared\Contracts\Inventory\ReserveStockItemData;
use App\Shared\Contracts\Inventory\StockAdjustmentData;
use App\Shared\Events\Inventory\LowStockDetected;
use App\Shared\Events\Inventory\StockAdjusted;
use App\Shared\Events\Inventory\StockReservationCommitted;
use App\Shared\Events\Inventory\StockReservationExpired;
use App\Shared\Events\Inventory\StockReservationReleased;
use App\Shared\Events\Inventory\StockReserved;
use App\Shared\Exceptions\Inventory\ImmutableMovementException;
use App\Shared\Exceptions\Inventory\InsufficientStockException;
use App\Shared\Exceptions\Inventory\InvalidReservationStateException;
use App\Shared\Exceptions\Inventory\NegativeStockException;
use App\Shared\Exceptions\Inventory\ReservationExpiredException;
use App\Shared\Exceptions\Inventory\UnauthorizedInventoryMutationException;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class InventoryIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $inventoryManager;
    protected InventoryServiceContract $inventoryService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->inventoryManager = User::factory()->staff('inventory_manager')->create([
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);

        $this->inventoryService = $this->app->make(InventoryServiceContract::class);
    }

    // ─── 1. Warehouse & Multi-Warehouse Variant Inventory ──────────────

    public function test_admin_can_manage_warehouses_and_default_warehouse_switches_automatically(): void
    {
        $resp1 = $this->actingAs($this->inventoryManager, 'sanctum')
            ->postJson('/api/admin/inventory/warehouses', [
                'name'     => 'Dhaka Central Warehouse',
                'code'     => 'wh-dhk-01',
                'city'     => 'Dhaka',
                'priority' => 1,
            ]);

        $resp1->assertStatus(201)
            ->assertJsonPath('data.code', 'WH-DHK-01')
            ->assertJsonPath('data.is_default', true);

        $wh1Id = $resp1->json('data.id');

        $resp2 = $this->actingAs($this->inventoryManager, 'sanctum')
            ->postJson('/api/admin/inventory/warehouses', [
                'name'       => 'Chattogram Port Warehouse',
                'code'       => 'wh-ctg-01',
                'city'       => 'Chattogram',
                'is_default' => true,
                'priority'   => 2,
            ]);

        $resp2->assertStatus(201)
            ->assertJsonPath('data.code', 'WH-CTG-01')
            ->assertJsonPath('data.is_default', true);

        $this->assertDatabaseHas('warehouses', ['id' => $wh1Id, 'is_default' => false]);
    }

    public function test_cannot_delete_warehouse_with_existing_on_hand_or_reserved_stock(): void
    {
        $warehouse = Warehouse::factory()->defaultWarehouse()->create();
        $variant   = ProductVariant::factory()->create();

        $this->inventoryService->adjustStock(new StockAdjustmentData(
            productVariantId: $variant->id,
            warehouseId: $warehouse->id,
            quantityDelta: 10,
            type: InventoryMovement::TYPE_INITIAL,
            reason: 'Initial stock'
        ));

        $response = $this->actingAs($this->inventoryManager, 'sanctum')
            ->deleteJson("/api/admin/inventory/warehouses/{$warehouse->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('warehouses', ['id' => $warehouse->id, 'deleted_at' => null]);
    }

    public function test_variant_inventory_aggregates_across_multiple_active_warehouses(): void
    {
        $whDhaka = Warehouse::factory()->defaultWarehouse()->create(['code' => 'WH-DHK', 'priority' => 1]);
        $whCtg   = Warehouse::factory()->create(['code' => 'WH-CTG', 'is_active' => true, 'priority' => 2]);
        $whInact = Warehouse::factory()->inactive()->create(['code' => 'WH-INACTIVE']);

        $variant = ProductVariant::factory()->create(['sku' => 'TEE-BLK-L', 'status' => 'active']);

        $this->inventoryService->adjustStock(new StockAdjustmentData($variant->id, $whDhaka->id, 30, 'restock'));
        $this->inventoryService->adjustStock(new StockAdjustmentData($variant->id, $whCtg->id, 20, 'restock'));
        $this->inventoryService->adjustStock(new StockAdjustmentData($variant->id, $whInact->id, 100, 'restock'));

        // Reserve 8 units in Dhaka
        $this->inventoryService->reserveStock(new ReserveStockData(
            idempotencyKey: 'res-multi-wh-check',
            items: [new ReserveStockItemData($variant->id, 8, $whDhaka->id)]
        ));

        // Active warehouses have (30 - 8) + 20 = 42 available (inactive warehouse is excluded)
        $this->assertSame(42, $this->inventoryService->getAvailableStock($variant->id));
        $this->assertSame(22, $this->inventoryService->getAvailableStock($variant->id, $whDhaka->id));
        $this->assertSame(20, $this->inventoryService->getAvailableStock($variant->id, $whCtg->id));

        $summary = $this->inventoryService->getStockSummaryForVariant($variant->id);
        $this->assertSame(50, $summary->totalOnHand);
        $this->assertSame(8, $summary->totalReserved);
        $this->assertSame(42, $summary->totalAvailable);
        $this->assertTrue($summary->inStock);
        $this->assertCount(2, $summary->warehouseBreakdown);

        // Public storefront availability endpoint
        $storeResp = $this->getJson("/api/inventory/variants/{$variant->id}/availability");
        $storeResp->assertOk()
            ->assertJsonPath('data.sku', 'TEE-BLK-L')
            ->assertJsonPath('data.in_stock', true)
            ->assertJsonPath('data.available_quantity', 42);
    }

    // ─── 2. Stock Adjustments, Transfers & Movement History ────────────

    public function test_every_stock_adjustment_creates_an_inventory_movement_and_prevents_negative_stock(): void
    {
        Event::fake([StockAdjusted::class]);

        $warehouse = Warehouse::factory()->defaultWarehouse()->create();
        $variant   = ProductVariant::factory()->create();

        // 1. Add 25 units via Admin API
        $resp = $this->actingAs($this->inventoryManager, 'sanctum')
            ->postJson('/api/admin/inventory/adjust', [
                'product_variant_id' => $variant->id,
                'warehouse_id'       => $warehouse->id,
                'quantity_delta'     => 25,
                'type'               => 'restock',
                'reason'             => 'Purchase Order PO-1001',
                'reference_type'     => 'purchase_order',
                'reference_id'       => 'PO-1001',
            ]);

        $resp->assertStatus(201)
            ->assertJsonPath('data.quantity_before', 0)
            ->assertJsonPath('data.quantity_after', 25)
            ->assertJsonPath('data.on_hand_delta', 25)
            ->assertJsonPath('data.available_after', 25);

        Event::assertDispatched(StockAdjusted::class);

        // 2. Deduct 5 units for damage
        $resp2 = $this->actingAs($this->inventoryManager, 'sanctum')
            ->postJson('/api/admin/inventory/adjust', [
                'product_variant_id' => $variant->id,
                'warehouse_id'       => $warehouse->id,
                'quantity_delta'     => -5,
                'type'               => 'damage',
                'reason'             => 'Water damage',
            ]);

        $resp2->assertStatus(201)
            ->assertJsonPath('data.quantity_before', 25)
            ->assertJsonPath('data.quantity_after', 20)
            ->assertJsonPath('data.available_after', 20);

        // 3. Attempt to deduct 25 units when only 20 are available -> must fail with 422
        $failResp = $this->actingAs($this->inventoryManager, 'sanctum')
            ->postJson('/api/admin/inventory/adjust', [
                'product_variant_id' => $variant->id,
                'warehouse_id'       => $warehouse->id,
                'quantity_delta'     => -25,
                'type'               => 'adjustment',
            ]);

        $failResp->assertStatus(422);

        // Verify stock remained 20 and only 2 movements exist
        $this->assertSame(20, $this->inventoryService->getAvailableStock($variant->id, $warehouse->id));
        $this->assertSame(2, InventoryMovement::where('product_variant_id', $variant->id)->count());
    }

    public function test_cannot_adjust_or_set_on_hand_below_reserved_quantity(): void
    {
        $warehouse = Warehouse::factory()->defaultWarehouse()->create();
        $variant   = ProductVariant::factory()->create();

        $this->inventoryService->adjustStock(new StockAdjustmentData($variant->id, $warehouse->id, 15, 'restock'));
        $this->inventoryService->reserveStock(new ReserveStockData(
            idempotencyKey: 'res-lock-10',
            items: [new ReserveStockItemData($variant->id, 10, $warehouse->id)]
        ));

        // On hand = 15, Reserved = 10, Available = 5.
        // Attempting to deduct 6 units from on_hand would make available = -1 -> must throw InsufficientStockException!
        try {
            $this->inventoryService->adjustStock(new StockAdjustmentData($variant->id, $warehouse->id, -6, 'damage'));
            $this->fail('Expected InsufficientStockException was not thrown.');
        } catch (InsufficientStockException $e) {
            $this->assertInstanceOf(NegativeStockException::class, $e);
            $this->assertSame(5, $e->availableQuantity);
        }

        // Attempting to set stock on hand to 8 (< 10 reserved) via API -> 422
        $setResp = $this->actingAs($this->inventoryManager, 'sanctum')
            ->postJson('/api/admin/inventory/set-stock', [
                'product_variant_id' => $variant->id,
                'warehouse_id'       => $warehouse->id,
                'quantity_on_hand'   => 8,
            ]);

        $setResp->assertStatus(422);
        $this->assertSame(5, $this->inventoryService->getAvailableStock($variant->id, $warehouse->id));
    }

    public function test_stock_transfer_between_warehouses_creates_paired_movements(): void
    {
        $whSource = Warehouse::factory()->defaultWarehouse()->create(['code' => 'WH-SRC']);
        $whDest   = Warehouse::factory()->create(['code' => 'WH-DST']);
        $variant  = ProductVariant::factory()->create();

        $this->inventoryService->adjustStock(new StockAdjustmentData($variant->id, $whSource->id, 40, 'restock'));

        $resp = $this->actingAs($this->inventoryManager, 'sanctum')
            ->postJson('/api/admin/inventory/transfer', [
                'product_variant_id'       => $variant->id,
                'source_warehouse_id'      => $whSource->id,
                'destination_warehouse_id' => $whDest->id,
                'quantity'                 => 15,
                'reason'                   => 'Rebalance regional inventory',
            ]);

        $resp->assertStatus(201)
            ->assertJsonPath('data.out_movement.type', 'transfer_out')
            ->assertJsonPath('data.out_movement.on_hand_delta', -15)
            ->assertJsonPath('data.in_movement.type', 'transfer_in')
            ->assertJsonPath('data.in_movement.on_hand_delta', 15);

        $this->assertSame(25, $this->inventoryService->getAvailableStock($variant->id, $whSource->id));
        $this->assertSame(15, $this->inventoryService->getAvailableStock($variant->id, $whDest->id));
        $this->assertSame(40, $this->inventoryService->getAvailableStock($variant->id));
    }

    public function test_inventory_movements_are_immutable(): void
    {
        $warehouse = Warehouse::factory()->defaultWarehouse()->create();
        $variant   = ProductVariant::factory()->create();

        $this->inventoryService->adjustStock(new StockAdjustmentData($variant->id, $warehouse->id, 10, 'restock'));
        $movement = InventoryMovement::firstOrFail();

        $this->expectException(ImmutableMovementException::class);
        $movement->update(['quantity_change' => 999]);
    }

    // ─── 3. Reservation Lifecycle, Multi-Warehouse Allocation & Idempotency ─

    public function test_idempotent_reservation_creation_commit_and_release(): void
    {
        Event::fake([
            StockReserved::class,
            StockReservationCommitted::class,
            StockReservationReleased::class,
        ]);

        $warehouse = Warehouse::factory()->defaultWarehouse()->create();
        $variant   = ProductVariant::factory()->create();

        $this->inventoryService->adjustStock(new StockAdjustmentData($variant->id, $warehouse->id, 50, 'restock'));

        $reservePayload = new ReserveStockData(
            idempotencyKey: 'checkout-order-1001',
            items: [new ReserveStockItemData($variant->id, 12, $warehouse->id)],
            ttlMinutes: 20,
            referenceType: 'order',
            referenceId: 'ORD-1001'
        );

        // Call reserveStock twice with the exact same idempotencyKey
        $res1 = $this->inventoryService->reserveStock($reservePayload);
        $res2 = $this->inventoryService->reserveStock($reservePayload);

        $this->assertSame($res1->id, $res2->id);
        $this->assertSame(38, $this->inventoryService->getAvailableStock($variant->id, $warehouse->id));

        // Only 1 reservation movement was recorded despite duplicate call
        $this->assertSame(
            1,
            InventoryMovement::where('type', InventoryMovement::TYPE_RESERVATION)->count()
        );
        Event::assertDispatchedTimes(StockReserved::class, 1);

        // Commit reservation twice (idempotent commit)
        $committed1 = $this->inventoryService->commitReservation('checkout-order-1001');
        $committed2 = $this->inventoryService->commitReservation($res1->id);

        $this->assertSame('committed', $committed1->status);
        $this->assertSame('committed', $committed2->status);

        $summary = $this->inventoryService->getStockSummaryForVariant($variant->id);
        $this->assertSame(38, $summary->totalOnHand);
        $this->assertSame(0, $summary->totalReserved);
        $this->assertSame(38, $summary->totalAvailable);

        $this->assertSame(
            1,
            InventoryMovement::where('type', InventoryMovement::TYPE_RESERVATION_COMMIT)->count()
        );
        Event::assertDispatchedTimes(StockReservationCommitted::class, 1);

        // Attempting to release an already committed reservation must fail
        $this->expectException(InvalidReservationStateException::class);
        $this->inventoryService->releaseReservation($res1->id);
    }

    public function test_reservation_release_is_idempotent_and_restores_available_stock(): void
    {
        $warehouse = Warehouse::factory()->defaultWarehouse()->create();
        $variant   = ProductVariant::factory()->create();

        $this->inventoryService->adjustStock(new StockAdjustmentData($variant->id, $warehouse->id, 20, 'restock'));

        $res = $this->inventoryService->reserveStock(new ReserveStockData(
            idempotencyKey: 'cart-release-test',
            items: [new ReserveStockItemData($variant->id, 7, $warehouse->id)]
        ));

        $this->assertSame(13, $this->inventoryService->getAvailableStock($variant->id, $warehouse->id));

        // Release twice
        $rel1 = $this->inventoryService->releaseReservation($res->id, 'Payment cancelled');
        $rel2 = $this->inventoryService->releaseReservation('cart-release-test', 'Payment cancelled');

        $this->assertSame('released', $rel1->status);
        $this->assertSame('released', $rel2->status);
        $this->assertSame(20, $this->inventoryService->getAvailableStock($variant->id, $warehouse->id));

        $this->assertSame(
            1,
            InventoryMovement::where('type', InventoryMovement::TYPE_RESERVATION_RELEASE)->count()
        );

        // Attempting to commit a released reservation must fail
        $this->expectException(InvalidReservationStateException::class);
        $this->inventoryService->commitReservation($res->id);
    }

    public function test_automatic_multi_warehouse_reservation_splits_across_priority_warehouses(): void
    {
        $whPrimary   = Warehouse::factory()->defaultWarehouse()->create(['code' => 'WH-PRI', 'priority' => 1]);
        $whSecondary = Warehouse::factory()->create(['code' => 'WH-SEC', 'is_active' => true, 'priority' => 2]);

        $variant = ProductVariant::factory()->create();

        // 10 in Primary, 15 in Secondary (Total = 25)
        $this->inventoryService->adjustStock(new StockAdjustmentData($variant->id, $whPrimary->id, 10, 'restock'));
        $this->inventoryService->adjustStock(new StockAdjustmentData($variant->id, $whSecondary->id, 15, 'restock'));

        // Request 18 units without specifying warehouse_id -> should take 10 from WH-PRI and 8 from WH-SEC
        $reservation = $this->inventoryService->reserveStock(new ReserveStockData(
            idempotencyKey: 'split-allocation-key',
            items: [new ReserveStockItemData($variant->id, 18, null)]
        ));

        $this->assertCount(2, $reservation->items);
        $this->assertSame(0, $this->inventoryService->getAvailableStock($variant->id, $whPrimary->id));
        $this->assertSame(7, $this->inventoryService->getAvailableStock($variant->id, $whSecondary->id));
        $this->assertSame(7, $this->inventoryService->getAvailableStock($variant->id));
    }

    // ─── 4. Scheduled Reservation Expiry ───────────────────────────────

    public function test_scheduled_reservation_expiry_command_and_job_release_overdue_stock(): void
    {
        Event::fake([StockReservationExpired::class]);

        $warehouse = Warehouse::factory()->defaultWarehouse()->create();
        $variant   = ProductVariant::factory()->create();

        $this->inventoryService->adjustStock(new StockAdjustmentData($variant->id, $warehouse->id, 30, 'restock'));

        // Create an already-expired reservation (expires_at in the past)
        $expiredRes = $this->inventoryService->reserveStock(new ReserveStockData(
            idempotencyKey: 'expired-res-01',
            items: [new ReserveStockItemData($variant->id, 10, $warehouse->id)],
            expiresAt: now()->subMinutes(2)
        ));

        // Create an active non-expired reservation
        $activeRes = $this->inventoryService->reserveStock(new ReserveStockData(
            idempotencyKey: 'active-res-02',
            items: [new ReserveStockItemData($variant->id, 5, $warehouse->id)],
            expiresAt: now()->addMinutes(20)
        ));

        $this->assertSame(15, $this->inventoryService->getAvailableStock($variant->id, $warehouse->id));

        // Attempting to commit the expired reservation must throw ReservationExpiredException
        try {
            $this->inventoryService->commitReservation($expiredRes->id);
            $this->fail('Expected ReservationExpiredException was not thrown.');
        } catch (ReservationExpiredException) {
            // expected
        }

        // Run the scheduled artisan command
        $this->artisan('inventory:expire-reservations')
            ->assertExitCode(0);

        // Available stock is now 30 - 5 = 25 (10 units from expiredRes were restored)
        $this->assertSame(25, $this->inventoryService->getAvailableStock($variant->id, $warehouse->id));
        $this->assertSame('expired', $this->inventoryService->getReservation($expiredRes->id)->status);
        $this->assertSame('active', $this->inventoryService->getReservation($activeRes->id)->status);

        $this->assertSame(
            1,
            InventoryMovement::where('type', InventoryMovement::TYPE_RESERVATION_EXPIRED)->count()
        );
        Event::assertDispatchedTimes(StockReservationExpired::class, 1);

        // Also verify ExpireStockReservationsJob has tries=3 and timeout=3600 per AGENTS.md
        $job = new ExpireStockReservationsJob();
        $this->assertSame(3, $job->tries);
        $this->assertSame(3600, $job->timeout);
    }

    // ─── 5. Low-Stock Notifications ────────────────────────────────────

    public function test_low_stock_detection_dispatches_event_and_sends_notification_once_until_replenished(): void
    {
        Notification::fake();
        Event::fake([LowStockDetected::class]);

        $warehouse = Warehouse::factory()->defaultWarehouse()->create(['code' => 'WH-MAIN']);
        $variant   = ProductVariant::factory()->create(['sku' => 'LOW-SKU-001']);

        // Set initial stock to 10 with low_stock_threshold = 5
        $this->actingAs($this->inventoryManager, 'sanctum')
            ->postJson('/api/admin/inventory/set-stock', [
                'product_variant_id'  => $variant->id,
                'warehouse_id'        => $warehouse->id,
                'quantity_on_hand'    => 10,
                'low_stock_threshold' => 5,
            ])->assertStatus(201);

        Event::assertNotDispatched(LowStockDetected::class);

        // Reserve 6 units -> available drops to 4 (<= 5 threshold) -> triggers LowStockDetected + Notification!
        $this->inventoryService->reserveStock(new ReserveStockData(
            idempotencyKey: 'trigger-low-stock',
            items: [new ReserveStockItemData($variant->id, 6, $warehouse->id)]
        ));

        Event::assertDispatchedTimes(LowStockDetected::class, 1);
        Notification::assertSentTo($this->inventoryManager, LowStockAlertNotification::class);

        // Reserve 1 more unit (available = 3, still <= 5) -> should NOT spam a second notification
        $this->inventoryService->reserveStock(new ReserveStockData(
            idempotencyKey: 'still-low-stock',
            items: [new ReserveStockItemData($variant->id, 1, $warehouse->id)]
        ));

        Event::assertDispatchedTimes(LowStockDetected::class, 1);

        // Replenish +10 units (available = 13 > 5) -> resets low_stock_notified_at
        $this->inventoryService->adjustStock(new StockAdjustmentData($variant->id, $warehouse->id, 10, 'restock'));
        $item = InventoryItem::where('product_variant_id', $variant->id)->firstOrFail();
        $this->assertNull($item->low_stock_notified_at);

        // Drop below threshold again (-9 units -> available = 4 <= 5) -> triggers fresh LowStockDetected!
        $this->inventoryService->adjustStock(new StockAdjustmentData($variant->id, $warehouse->id, -9, 'damage'));
        Event::assertDispatchedTimes(LowStockDetected::class, 2);
    }

    // ─── 6. Inventory Reporting ────────────────────────────────────────

    public function test_inventory_reporting_endpoints_return_accurate_metrics_and_valuations(): void
    {
        $wh1 = Warehouse::factory()->defaultWarehouse()->create(['code' => 'WH-01']);
        $wh2 = Warehouse::factory()->create(['code' => 'WH-02', 'is_active' => true]);

        $product = Product::factory()->create(['name' => 'Oxford Shirt']);
        $v1 = ProductVariant::factory()->create([
            'product_id'   => $product->id,
            'sku'          => 'OXF-BLU-M',
            'price_amount' => 200000, // 2000.00 BDT in paisa
            'cost_price'   => 120000, // 1200.00 BDT in paisa
        ]);
        $v2 = ProductVariant::factory()->create([
            'product_id'   => $product->id,
            'sku'          => 'OXF-WHT-L',
            'price_amount' => 250000,
            'cost_price'   => 150000,
        ]);

        // v1 in wh1: 10 on hand (above threshold 5)
        $this->inventoryService->adjustStock(new StockAdjustmentData($v1->id, $wh1->id, 10, 'restock'));
        // v2 in wh2: 3 on hand (low stock <= 5)
        $this->inventoryService->adjustStock(new StockAdjustmentData($v2->id, $wh2->id, 3, 'restock'));

        // Reserve 2 units of v1 in wh1
        $this->inventoryService->reserveStock(new ReserveStockData(
            idempotencyKey: 'rep-res-1',
            items: [new ReserveStockItemData($v1->id, 2, $wh1->id)]
        ));

        // 1. Summary Report
        $summaryResp = $this->actingAs($this->inventoryManager, 'sanctum')
            ->getJson('/api/admin/inventory/reports/summary');

        $summaryResp->assertOk()
            ->assertJsonPath('data.total_warehouses', 2)
            ->assertJsonPath('data.total_inventory_items', 2)
            ->assertJsonPath('data.total_quantity_on_hand', 13)
            ->assertJsonPath('data.total_quantity_reserved', 2)
            ->assertJsonPath('data.total_available_quantity', 11)
            ->assertJsonPath('data.low_stock_items_count', 1)
            ->assertJsonPath('data.active_reservations_count', 1)
            ->assertJsonPath('data.valuation.cost_amount', (10 * 120000) + (3 * 150000))
            ->assertJsonPath('data.valuation.retail_amount', (10 * 200000) + (3 * 250000));

        // 2. Low-Stock Report
        $lowStockResp = $this->actingAs($this->inventoryManager, 'sanctum')
            ->getJson('/api/admin/inventory/reports/low-stock');

        $lowStockResp->assertOk();
        $this->assertCount(1, $lowStockResp->json('data'));
        $this->assertSame($v2->id, $lowStockResp->json('data.0.product_variant_id'));

        // 3. Warehouse Breakdown Report
        $whReportResp = $this->actingAs($this->inventoryManager, 'sanctum')
            ->getJson('/api/admin/inventory/reports/warehouses');

        $whReportResp->assertOk();
        $this->assertCount(2, $whReportResp->json('data'));

        // 4. Movements Summary Report
        $movReportResp = $this->actingAs($this->inventoryManager, 'sanctum')
            ->getJson('/api/admin/inventory/reports/movements');

        $movReportResp->assertOk();
        $types = collect($movReportResp->json('data'))->pluck('type');
        $this->assertTrue($types->contains('restock'));
        $this->assertTrue($types->contains('reservation'));
    }

    // ─── 7. Domain Isolation & Direct Table Update Protection ──────────

    public function test_direct_update_to_inventory_item_quantities_outside_inventory_service_is_blocked(): void
    {
        $warehouse = Warehouse::factory()->defaultWarehouse()->create();
        $variant   = ProductVariant::factory()->create();

        $this->inventoryService->adjustStock(new StockAdjustmentData($variant->id, $warehouse->id, 20, 'restock'));

        $item = InventoryItem::where('product_variant_id', $variant->id)->firstOrFail();

        $this->expectException(UnauthorizedInventoryMutationException::class);
        $item->update(['quantity_on_hand' => 100]);
    }

    public function test_catalog_and_orders_modules_do_not_directly_update_inventory_tables(): void
    {
        $modulesToCheck = [
            base_path('app/Modules/Catalog'),
            base_path('app/Modules/Orders'),
        ];

        $forbiddenPatterns = [
            'App\\Modules\\Inventory\\Models\\',
            "DB::table('inventory_items')",
            'DB::table("inventory_items")',
            "DB::table('inventory_movements')",
            'DB::table("inventory_movements")',
            "DB::table('stock_reservations')",
            'DB::table("stock_reservations")',
            "DB::table('stock_reservation_items')",
            'DB::table("stock_reservation_items")',
            "DB::table('warehouses')",
            'DB::table("warehouses")',
        ];

        foreach ($modulesToCheck as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());
                foreach ($forbiddenPatterns as $pattern) {
                    $this->assertStringNotContainsString(
                        $pattern,
                        $contents,
                        "Module isolation violation in {$file->getPathname()}: found forbidden reference '{$pattern}'."
                    );
                }
            }
        }

        $this->assertTrue(true);
    }
}

