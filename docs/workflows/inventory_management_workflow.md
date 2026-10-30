# Inventory Management Workflow

## Overview

This document describes the inventory management system: how stock is tracked, reserved during checkout, adjusted by admins, and reconciled across warehouse operations.

---

## Core Inventory Concepts

| Concept              | Definition                                               |
|----------------------|----------------------------------------------------------|
| `quantity_on_hand`   | Physical stock in warehouse                              |
| `quantity_reserved`  | Stock held for pending/confirmed orders (not yet shipped)|
| **Available**        | `quantity_on_hand - quantity_reserved`                   |

> **Rule:** Available stock is never stored — always computed. This prevents inconsistencies.

---

## Inventory Item Structure

Each sellable variant has one `inventory_items` record per warehouse:

```sql
inventory_items
├── id
├── variant_id          -- FK: product_variants.id
├── warehouse_id        -- FK: warehouses.id
├── quantity_on_hand    -- INTEGER >= 0
├── quantity_reserved   -- INTEGER >= 0
├── reorder_level       -- Alert threshold
├── created_at
└── updated_at

UNIQUE(variant_id, warehouse_id)
```

---

## Stock Adjustment Workflow (Admin)

```
Admin opens inventory panel
          │
          ▼
Select product variant
          │
          ▼
Choose adjustment type:
  ┌───────┴───────┐
 Add           Remove
  │               │
  ▼               ▼
Enter quantity  Enter quantity
  │               │
  ▼               ▼
Provide reason  Provide reason
  │               │
  └───────┬───────┘
          │
          ▼
POST /admin/inventory/adjustments
          │
          ▼
Begin DB transaction
          │
          ▼
Lock inventory_items row (FOR UPDATE)
          │
          ▼
Apply adjustment (quantity_on_hand += / -=)
          │
          ▼
Record inventory_movement
          │
          ▼
Commit transaction
          │
          ▼
Check reorder_level
  ┌───────┴───────┐
Below level    Above level
  │               │
  ▼               ▼
Dispatch     No action
low-stock alert
```

### Adjustment Types

| Type       | Effect on `quantity_on_hand` | Movement Reason              |
|------------|------------------------------|------------------------------|
| `receive`  | `+= quantity`                | Stock received from supplier |
| `return`   | `+= quantity`                | Customer return processed    |
| `damage`   | `-= quantity`                | Damaged goods written off    |
| `loss`     | `-= quantity`                | Shrinkage / theft            |
| `transfer` | Source `-=`, Destination `+=`| Warehouse transfer           |
| `correction` | `+= / -=`                 | Physical count correction    |

---

## Inventory Movement Record

Every adjustment **must** create an `inventory_movements` record:

```sql
inventory_movements
├── id
├── inventory_item_id
├── variant_id
├── warehouse_id
├── type                -- receive | damage | loss | correction | reservation | fulfilment | release
├── quantity_change     -- Positive or negative integer
├── quantity_before
├── quantity_after
├── reference_id        -- order_id, adjustment_id, etc.
├── reference_type      -- 'Order', 'Adjustment', etc.
├── notes
├── performed_by        -- user_id (admin or system)
├── created_at
```

> **Rule:** Inventory movements are **append-only**. Never update or delete them.

---

## Reservation Lifecycle

### Reservation Created (Order Placement)

```sql
-- Inside DB transaction during POST /checkout/orders
SELECT * FROM inventory_items WHERE id = ? FOR UPDATE;
-- Verify: (quantity_on_hand - quantity_reserved) >= ordered_quantity
UPDATE inventory_items SET quantity_reserved = quantity_reserved + ordered_quantity;
INSERT INTO stock_reservations (...);
INSERT INTO inventory_movements (type='reservation', ...);
```

### Reservation Expired (Unpaid Online Order)

Triggered by scheduled job every minute:

```sql
-- Find reservations expired after 15 minutes for unpaid orders
SELECT sr.* FROM stock_reservations sr
  JOIN payment_transactions pt ON pt.order_id = sr.order_id
WHERE sr.status = 'active'
  AND sr.created_at < NOW() - INTERVAL 15 MINUTE
  AND pt.status = 'pending';

-- For each: release reservation
UPDATE inventory_items SET quantity_reserved = quantity_reserved - reserved_quantity;
UPDATE stock_reservations SET status = 'expired';
INSERT INTO inventory_movements (type='release', ...);
```

### Reservation Fulfilled (Order Shipped)

```sql
-- On shipment creation
UPDATE inventory_items
  SET quantity_on_hand = quantity_on_hand - ordered_quantity,
      quantity_reserved = quantity_reserved - ordered_quantity;
UPDATE stock_reservations SET status = 'fulfilled';
INSERT INTO inventory_movements (type='fulfilment', ...);
```

### Reservation Released (Order Cancelled)

```sql
-- On cancellation
UPDATE inventory_items SET quantity_reserved = quantity_reserved - reserved_quantity;
UPDATE stock_reservations SET status = 'released';
INSERT INTO inventory_movements (type='release', ...);
```

---

## Low Stock Alerts

- If `quantity_on_hand - quantity_reserved <= reorder_level` after any adjustment or fulfilment, dispatch `LowStockAlert` event.
- Notification delivered to `inventory_manager` role via notification system.
- Alert cooldown: 24 hours per variant (prevent alert storms).

---

## Warehouse Structure

```sql
warehouses
├── id
├── name            -- 'Main Warehouse - Dhaka'
├── code            -- 'WH-DHAKA-01'
├── address_line
├── district
├── division
├── is_active
├── created_at
└── updated_at
```

For Phase 1, a single default warehouse is sufficient.

---

## Inventory Admin API Endpoints

| Method | Endpoint                              | Purpose                     | Permission          |
|--------|---------------------------------------|-----------------------------|---------------------|
| GET    | `/api/v1/admin/inventory`             | Stock overview / listing    | inventory_manager   |
| GET    | `/api/v1/admin/inventory/{variantId}` | Variant stock detail        | inventory_manager   |
| POST   | `/api/v1/admin/inventory/adjustments` | Manual stock adjustment     | inventory_manager   |
| GET    | `/api/v1/admin/inventory/movements`   | Movement history log        | inventory_manager   |
| GET    | `/api/v1/admin/inventory/low-stock`   | Low stock alert items       | inventory_manager   |
| GET    | `/api/v1/admin/warehouses`            | Warehouse list              | inventory_manager   |

---

## Concurrency Safety

- All inventory mutations must use pessimistic locking (`SELECT ... FOR UPDATE`).
- Never perform read-modify-write without locking.
- All reservation/release operations run inside explicit DB transactions.
- Atomic decrement: `UPDATE inventory_items SET quantity = quantity - N WHERE quantity >= N`.
