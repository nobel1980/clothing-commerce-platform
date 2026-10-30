# Backend Architecture Record

## 1. Domain Structure & Responsibility Matrix

The backend is organized into business domains inside `apps/api/app/Modules/`. Each module maintains strict encapsulation.

```
app/Modules/
├── Identity/      # Users, Roles, Permissions, Auth Sessions
├── Catalog/       # Brands, Categories, Products, Variants, Media
├── Inventory/     # Warehouses, Stock Levels, Adjustments, Reservations
├── Cart/          # Active guest & customer carts
├── Checkout/      # Pricing engine, Quote generation, Address validation
├── Orders/        # Order creation, Status state machine, History
├── Payments/      # Transactions, Gateway integrations, Refunds
├── Promotions/    # Discount coupons, Rules, Redemptions
├── Shipping/      # Courier integrations, Parcel booking, Tracking
├── Customers/     # Profiles, Addresses, Wishlists
├── Reviews/       # Product reviews and admin moderation
└── Notifications/ # Email, SMS, System notification handlers
```

---

## 2. Module Communication Rules

To maintain high cohesion and low coupling:

1. **Direct Imports:** Module `A` must NEVER directly reference Eloquent Models or internal classes of Module `B`.
2. **Synchronous Contracts:** Cross-module queries must go through explicit application service contracts located in `App\Modules\{Module}\Domain\Contracts\`.
3. **Cross-Module State Mutation:** Must occur asynchronously via **Domain Events** emitted to the **Transactional Outbox**.

### Example: Order Placement Flow

```
[Checkout Module]
    │
    ├── (Sync)   ──> [Catalog Module]: Validate Product/Variant Active Status
    ├── (Sync)   ──> [Promotions Module]: Calculate Valid Discount
    ├── (Sync)   ──> [Inventory Module]: Reserve Stock (Pessimistic Lock)
    ├── (Sync)   ──> [Orders Module]: Create Order & Snapshots
    ├── (Sync)   ──> [Payments Module]: Initialize Payment Record
    └── (DB Tx)  ──> Write "OrderPlaced" event to outbox_events table
```

---

## 3. Database Module Ownership & Rules

* Each module strictly owns its dedicated tables.
* Direct cross-module SQL joins or updates are strictly prohibited in application code.

| Module | Primary Tables Owned |
| :--- | :--- |
| `Identity` | `users`, `roles`, `permissions`, `model_has_roles`, `role_has_permissions` |
| `Catalog` | `brands`, `categories`, `products`, `product_images`, `product_variants`, `attributes`, `attribute_values` |
| `Inventory` | `warehouses`, `inventory_items`, `inventory_movements`, `stock_reservations`, `stock_reservation_items` |
| `Cart` | `carts`, `cart_items` |
| `Checkout` | `outbox_events`, `idempotency_keys` |
| `Orders` | `orders`, `order_items`, `order_status_histories` |
| `Payments` | `payment_transactions`, `payment_webhook_events`, `refunds` |
| `Promotions` | `coupons`, `coupon_rules`, `coupon_usages` |
| `Shipping` | `shipping_zones`, `shipping_methods`, `shipments`, `shipment_tracking_events` |
| `Customers` | `customer_addresses`, `wishlists`, `wishlist_items` |

---

## 4. Transactional Outbox Pattern

All cross-module events are saved to the `outbox_events` table within the active database transaction:

```sql
CREATE TABLE outbox_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    aggregate_type VARCHAR(100) NOT NULL,
    aggregate_id VARCHAR(100) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    payload JSON NOT NULL,
    occurred_at TIMESTAMP NOT NULL,
    published_at TIMESTAMP NULL,
    attempts INT DEFAULT 0,
    last_error TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

A dedicated worker reads unpublished events (`published_at IS NULL`) and dispatches them to internal queue workers or message systems.