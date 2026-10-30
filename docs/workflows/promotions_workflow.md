# Promotions & Coupon Workflow

## Overview

This document describes the promotional system including coupon creation, discount calculation, validation rules, and usage limit enforcement.

---

## Coupon Types

| Type         | Example                              |
|--------------|--------------------------------------|
| `flat`       | ৳100 off any order                   |
| `percentage` | 15% off (optionally capped at max)   |
| `free_shipping` | Waive shipping charge             |

---

## Coupon Data Model

```sql
coupons
├── id
├── code                   -- 'SUMMER15', 'WELCOME100' (unique, uppercase)
├── type                   -- flat | percentage | free_shipping
├── value                  -- Discount amount or percentage
├── max_discount_amount    -- Cap for percentage discounts (nullable)
├── min_order_amount       -- Minimum subtotal to activate (nullable)
├── usage_limit            -- Total uses allowed (nullable = unlimited)
├── usage_per_customer     -- Max uses per customer (nullable)
├── is_active
├── starts_at
├── expires_at
├── created_by             -- admin user_id
├── created_at
└── updated_at

coupon_usages
├── id
├── coupon_id
├── user_id
├── order_id
├── discount_applied       -- DECIMAL(12,2)
├── used_at
```

---

## Coupon Validation Workflow

```
Customer enters coupon code at checkout
          │
          ▼
POST /api/v1/checkout/quote (with coupon_code)
          │
          ▼
Validate coupon:
  1. Code exists and is active
  2. Current date within starts_at / expires_at window
  3. Order subtotal >= min_order_amount (if set)
  4. Global usage_limit not exceeded
  5. Per-customer limit not exceeded (if authenticated)
          │
     ┌────┴────┐
   Invalid    Valid
     │           │
     ▼           ▼
  Return       Apply discount to checkout quote
  error msg
               │
               ▼
         Calculate discount:
           flat:       discount = value
           percentage: discount = min(subtotal × value%, max_discount_amount)
           free_shipping: shipping_charge = 0
               │
               ▼
         Return updated quote total
```

---

## Coupon Application at Order Placement

```
POST /api/v1/checkout/orders
  { quote_id, coupon_code, ... }
          │
          ▼
Re-validate coupon (re-check all conditions within DB transaction)
          │
          ▼
Apply discount to order record:
  orders.coupon_code = 'SUMMER15'
  orders.discount_amount = calculated_discount
          │
          ▼
INSERT coupon_usages
  (coupon_id, user_id, order_id, discount_applied)
          │
          ▼
Increment coupon.used_count (if tracked in coupons table)
```

> **Rule:** Coupon validation and application must happen inside the same DB transaction as order creation.

---

## Pricing Engine Calculation

```
Subtotal (sum of variant_price × quantity per line)
  − Promotional Discounts (future: automatic campaigns)
  − Coupon Discount (flat / percentage)
  + Shipping Charge
  + Tax (configurable, initially 0%)
  ─────────────────────────────────
  = Final Order Total
```

> **Rule:** Final pricing is always computed server-side. The frontend must never calculate final totals independently.

---

## Admin Coupon Management API

| Method | Endpoint                          | Purpose               | Permission         |
|--------|-----------------------------------|-----------------------|--------------------|
| GET    | `/api/v1/admin/promotions/coupons`       | List coupons   | marketing_manager  |
| POST   | `/api/v1/admin/promotions/coupons`       | Create coupon  | marketing_manager  |
| GET    | `/api/v1/admin/promotions/coupons/{id}`  | Coupon detail  | marketing_manager  |
| PATCH  | `/api/v1/admin/promotions/coupons/{id}`  | Update coupon  | marketing_manager  |
| DELETE | `/api/v1/admin/promotions/coupons/{id}`  | Delete coupon  | marketing_manager  |
| GET    | `/api/v1/admin/promotions/coupons/{id}/usages` | Usage report | marketing_manager |

---

## Anti-Abuse Guards

- **Unique usage recording:** One `coupon_usages` record per order.
- **Re-validation on order placement:** Prevents race conditions from concurrent requests exhausting usage limits.
- **Pessimistic locking on usage check:** Use DB-level locking for usage limit enforcement.
- **Case-insensitive code matching:** Normalize to uppercase on both creation and lookup.
- **Coupon stacking:** Not supported in Phase 1. One coupon per order only.
