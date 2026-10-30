# Order Lifecycle Workflow

## Overview

This document describes the complete lifecycle of an order from placement through delivery, including payment state, fulfilment transitions, and cancellation/refund rules.

---

## Order Status State Machine

```
                         ┌──────────────────────────────────────────────────┐
                         ▼                                                    │
                    [pending]                                                  │
                    /       \                                                  │
              COD           Online Payment                                     │
               │                  │                                           │
               ▼                  ▼                                           │
       [pending_confirmation]  [awaiting_payment]                             │
               │               /         \                                    │
               │         Payment OK    Payment Fail / Expired                 │
               │              │               │                               │
               ▼              ▼               ▼                               │
          [confirmed]    [confirmed]    [payment_failed]                      │
               │              │                                               │
               ▼              ▼                                               │
          [processing]  [processing]                                          │
               │              │                                               │
               ▼              ▼                                               │
            [shipped]    [shipped]                                            │
               │              │                                               │
               ▼              ▼                                               │
           [delivered]   [delivered]                                          │
                                                                              │
           [cancelled] ──────────────────────────────────────────────────────┘
```

---

## Payment Status State Machine

```
[pending] ──────────► [paid]
    │
    ├──────────────► [failed]
    │
    └──────────────► [refunded]
                          ▲
                    [paid] ──► [refunded]
```

---

## Fulfilment Status State Machine

```
[unfulfilled] ──────────► [partially_fulfilled]
       │                           │
       └──────────► [fulfilled] ◄──┘
                         │
                         ▼
                   [delivered]
                         │
                   [returned] (optional)
```

---

## Detailed Status Transitions

| From                   | To                   | Trigger                              | Actor          |
|------------------------|----------------------|--------------------------------------|----------------|
| —                      | `pending`            | Order placed                         | System         |
| `pending` (COD)        | `pending_confirmation` | COD order awaits admin confirmation | System         |
| `pending` (online)     | `awaiting_payment`   | Gateway session initiated            | System         |
| `pending_confirmation` | `confirmed`          | Admin confirms COD order             | order_manager  |
| `awaiting_payment`     | `confirmed`          | Payment verified by gateway webhook  | System         |
| `awaiting_payment`     | `payment_failed`     | Payment timeout or gateway failure   | System/Worker  |
| `confirmed`            | `processing`         | Admin starts fulfilment              | order_manager  |
| `processing`           | `shipped`            | Courier consignment created          | order_manager  |
| `shipped`              | `delivered`          | Delivery confirmed by courier        | System/Courier |
| Any active status      | `cancelled`          | Admin or customer cancels            | order_manager  |
| `confirmed`            | `cancelled`          | Admin cancels before shipping        | order_manager  |
| `delivered`            | `returned`           | Customer/admin initiates return      | order_manager  |

---

## Order Number Generation

Format: `ORD-{YYYYMMDD}-{6-digit-sequence}`

Example: `ORD-20260115-000042`

- Must be unique (unique DB constraint enforced).
- Generated atomically at order creation within the DB transaction.
- Never reuse order numbers after cancellation.

---

## Address Snapshots

At order placement, the delivery and billing addresses are **snapshotted** as JSON on the `orders` table:

```json
{
  "recipient_name": "Rahim Uddin",
  "phone": "+8801711000000",
  "division": "Dhaka",
  "district": "Dhaka",
  "upazila": "Gulshan",
  "area": "Gulshan 2",
  "address_line": "House 12, Road 4",
  "postal_code": "1212",
  "delivery_instruction": "Call before delivery"
}
```

> **Rule:** Snapshots are immutable after order placement. Address changes on the customer profile never retroactively affect existing orders.

---

## Pricing Snapshot

Order items must store denormalized pricing at time of purchase:

```json
{
  "product_name": "Premium Cotton T-Shirt",
  "variant_name": "Black / M",
  "sku": "TSH-BLK-M",
  "quantity": 2,
  "unit_price": "1200.00",
  "discount_amount": "100.00",
  "line_total": "2300.00",
  "currency": "BDT"
}
```

---

## Cancellation Rules

| Condition                                      | Outcome                     |
|------------------------------------------------|-----------------------------|
| Order in `pending` or `pending_confirmation`   | Immediate cancellation      |
| Order in `awaiting_payment` (online)           | Cancel + release inventory  |
| Order in `confirmed` or `processing`           | Cancel + release inventory  |
| Order in `shipped`                             | Requires courier intercept  |
| Order in `delivered`                           | Cannot cancel — use refund  |

---

## Inventory Lifecycle During Order

| Event                          | Inventory Effect                               |
|--------------------------------|------------------------------------------------|
| Order placed (stage 2)         | `quantity_reserved += ordered_quantity`        |
| Payment confirmed              | No change to reservation                       |
| Order shipped                  | `quantity_on_hand -= ordered_quantity`, `quantity_reserved -= ordered_quantity` |
| Order cancelled/expired        | `quantity_reserved -= ordered_quantity`        |

---

## Order Events (Outbox)

| Event               | When Emitted              | Downstream Listeners                     |
|---------------------|---------------------------|------------------------------------------|
| `OrderPlaced`       | Order creation commit     | Notification, inventory, reporting       |
| `OrderConfirmed`    | Admin confirmation        | Notification, fulfilment trigger         |
| `OrderShipped`      | Consignment created       | Notification, tracking start             |
| `OrderDelivered`    | Delivery confirmed        | Notification, review prompt              |
| `OrderCancelled`    | Cancellation committed    | Inventory release, notification          |
| `PaymentConfirmed`  | Webhook verified          | Order status update, notification        |
| `RefundInitiated`   | Admin triggers refund     | Payment module, notification             |

---

## Admin API Endpoints

| Method | Endpoint                            | Purpose                         | Permission      |
|--------|-------------------------------------|---------------------------------|-----------------|
| GET    | `/api/v1/admin/orders`              | List all orders with filters    | order_manager   |
| GET    | `/api/v1/admin/orders/{id}`         | Order details                   | order_manager   |
| POST   | `/api/v1/admin/orders/{id}/confirm` | Confirm COD/pending order       | order_manager   |
| POST   | `/api/v1/admin/orders/{id}/ship`    | Create courier consignment      | order_manager   |
| POST   | `/api/v1/admin/orders/{id}/cancel`  | Cancel the order                | order_manager   |
| POST   | `/api/v1/admin/orders/{id}/refund`  | Initiate a refund               | order_manager   |

## Customer API Endpoints

| Method | Endpoint                       | Purpose                |
|--------|--------------------------------|------------------------|
| GET    | `/api/v1/orders`               | Customer's order list  |
| GET    | `/api/v1/orders/{orderNumber}` | Order detail & status  |
| POST   | `/api/v1/orders/{id}/cancel`   | Customer cancels order |
