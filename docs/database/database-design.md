# Database Architecture Record

## 1. Database Overview & Module Ownership

The system uses a single MySQL 8 instance with InnoDB storage engine (`clothing_commerce`). 

To prevent tight coupling within the modular monolith, tables are logically owned by specific domain modules. Cross-module database writes are strictly prohibited; state modifications across modules must occur via explicit service contracts or asynchronous domain events.

```
+-----------------------------------------------------------------------------------+
|                                  MYSQL 8 DATABASE                                 |
+-------------------+--------------------+--------------------+---------------------+
| Catalog Module    | Inventory Module   | Orders Module      | Payments Module     |
| - products        | - warehouses       | - orders           | - payment_          |
| - product_variants| - inventory_items  | - order_items      |   transactions      |
| - categories      | - inventory_       | - order_status_    | - payment_webhook_  |
| - brands          |   movements        |   histories        |   events            |
| - product_images  | - stock_           |                    | - refunds           |
| - attributes      |   reservations     |                    |                     |
+-------------------+--------------------+--------------------+---------------------+
| Promotions Module | Shipping Module    | Customers Module   | Platform / System   |
| - coupons         | - shipping_zones   | - customer_        | - outbox_events     |
| - coupon_rules    | - shipping_methods |   addresses        | - idempotency_keys  |
| - coupon_usages   | - shipments        | - wishlists        | - audit_logs        |
|                   | - tracking_events  | - reviews          |                     |
+-------------------+--------------------+--------------------+---------------------+
```

---

## 2. Table Schemas & Column Specifications

### Catalog Module

#### `products`
| Column | Type | Nullable | Index | Description |
| :--- | :--- | :--- | :--- | :--- |
| `id` | BIGINT UNSIGNED | No | Primary | Unique product identifier |
| `category_id` | BIGINT UNSIGNED | No | Foreign | Category reference |
| `brand_id` | BIGINT UNSIGNED | Yes | Foreign | Brand reference |
| `name` | VARCHAR(255) | No | Text Search | Product display name |
| `slug` | VARCHAR(255) | No | Unique | SEO friendly URL slug |
| `description` | TEXT | Yes | None | Full rich product description |
| `status` | VARCHAR(30) | No | Index | `draft`, `active`, `archived` |
| `is_featured` | BOOLEAN | No | Index | Showcase on homepage flag |
| `published_at` | TIMESTAMP | Yes | Index | Publication timestamp |
| `created_at` | TIMESTAMP | No | None | Record created |
| `updated_at` | TIMESTAMP | No | None | Record updated |

#### `product_variants`
| Column | Type | Nullable | Index | Description |
| :--- | :--- | :--- | :--- | :--- |
| `id` | BIGINT UNSIGNED | No | Primary | Unique variant identifier |
| `product_id` | BIGINT UNSIGNED | No | Foreign | Parent product reference |
| `sku` | VARCHAR(100) | No | Unique | Stock Keeping Unit |
| `barcode` | VARCHAR(100) | Yes | Index | EAN / UPC Barcode |
| `color` | VARCHAR(100) | Yes | Index | Display color string |
| `size` | VARCHAR(50) | Yes | Index | Display size string (S, M, L, XL) |
| `price` | DECIMAL(12,2) | No | Index | Selling price in BDT |
| `compare_at_price`| DECIMAL(12,2) | Yes | None | Original / Strike-through price |
| `cost_price` | DECIMAL(12,2) | Yes | None | Internal procurement cost |
| `status` | VARCHAR(30) | No | Index | `active`, `inactive` |

---

### Inventory Module

#### `inventory_items`
| Column | Type | Nullable | Index | Description |
| :--- | :--- | :--- | :--- | :--- |
| `id` | BIGINT UNSIGNED | No | Primary | Inventory record ID |
| `variant_id` | BIGINT UNSIGNED | No | Unique Key 1 | Product variant reference |
| `warehouse_id` | BIGINT UNSIGNED | No | Unique Key 2 | Warehouse location reference |
| `quantity_on_hand`| INT | No | None | Total physical stock |
| `quantity_reserved`| INT | No | None | Stock locked in active checkouts |
| `reorder_level` | INT | No | None | Low-stock alert threshold |

*Constraint:* `UNIQUE KEY (variant_id, warehouse_id)`.
*Formula:* $\text{Available Stock} = \text{quantity\_on\_hand} - \text{quantity\_reserved}$.

#### `inventory_movements`
| Column | Type | Nullable | Index | Description |
| :--- | :--- | :--- | :--- | :--- |
| `id` | BIGINT UNSIGNED | No | Primary | Movement log ID |
| `inventory_item_id`| BIGINT UNSIGNED| No | Foreign | Inventory item reference |
| `quantity_change` | INT | No | None | Positive or negative delta |
| `type` | VARCHAR(50) | No | Index | `purchase`, `sale`, `return`, `adjustment` |
| `reference_type` | VARCHAR(100) | Yes | Composite | Polymorphic association (`Order`, `Adjustment`) |
| `reference_id` | VARCHAR(100) | Yes | Composite | Associated reference ID |
| `notes` | TEXT | Yes | None | Audit notes for stock adjustment |
| `created_at` | TIMESTAMP | No | Index | Movement timestamp |

---

### Orders Module

#### `orders`
| Column | Type | Nullable | Index | Description |
| :--- | :--- | :--- | :--- | :--- |
| `id` | BIGINT UNSIGNED | No | Primary | Unique internal order ID |
| `order_number` | VARCHAR(50) | No | Unique | Customer-facing order code (e.g. `ORD-20261030-XXXX`) |
| `user_id` | BIGINT UNSIGNED | Yes | Foreign | Customer user ID (nullable for guest checkout) |
| `shipping_address_snapshot` | JSON | No | None | Complete recipient address snapshot |
| `billing_address_snapshot` | JSON | No | None | Billing details snapshot |
| `subtotal` | DECIMAL(12,2) | No | None | Sum of line items before discounts |
| `discount_amount` | DECIMAL(12,2) | No | None | Total coupon and rule discounts |
| `shipping_amount` | DECIMAL(12,2) | No | None | Shipping delivery charge |
| `tax_amount` | DECIMAL(12,2) | No | None | Applicable tax/VAT |
| `total_amount` | DECIMAL(12,2) | No | Index | Final calculated order total in BDT |
| `currency` | VARCHAR(3) | No | None | Always `BDT` |
| `order_status` | VARCHAR(30) | No | Index | `pending`, `confirmed`, `processing`, `shipped`, `delivered`, `cancelled` |
| `payment_status` | VARCHAR(30) | No | Index | `pending`, `paid`, `partially_refunded`, `refunded`, `failed` |
| `fulfillment_status`| VARCHAR(30) | No | Index | `unfulfilled`, `partially_fulfilled`, `fulfilled`, `returned` |
| `placed_at` | TIMESTAMP | No | Index | Order placement timestamp |

---

## 3. Monetary Integrity & Precision

1. **MySQL Storage Standard:** All prices, totals, shipping fees, and discount amounts MUST use `DECIMAL(12,2)`.
2. **PHP Computation Standard:**
   * Money calculations in PHP code MUST utilize integer poisha conversions ($\text{Poisha} = \text{BDT} \times 100$) or strict string-based math using `bcmath`.
   * Floating-point arithmetic (`float`/`double`) is prohibited for monetary values.