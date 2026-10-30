# Courier & Shipping Workflow

## Overview

This document describes the Bangladesh courier integration architecture: consignment creation, tracking synchronization, delivery handling, COD reconciliation, and failed delivery management.

---

## Supported Courier Providers

| Provider     | Priority | Notes                            |
|--------------|----------|----------------------------------|
| Pathao Courier | Primary  | Inside/outside Dhaka, COD       |
| Steadfast    | Secondary | COD-focused, widely used        |
| REDX         | Tertiary  | Growing network                 |

---

## Courier Interface Contract

```php
interface CourierProvider
{
    public function createParcel(ParcelRequest $request): ParcelResult;
    public function cancelParcel(string $consignmentId): void;
    public function trackParcel(string $consignmentId): TrackingResult;
    public function calculateCharge(ShippingQuoteRequest $request): ShippingCharge;
}
```

---

## Shipping Charge Calculation

```
Base charge determination:
  ├── Inside Dhaka:      ৳60–80 flat rate
  ├── Outside Dhaka:     ৳100–130 flat rate
  └── Express/Same-day:  Add-on charge

COD charge:
  ├── Typically 1–1.5% of COD collection amount
  └── Added to shipping total

Weight-based surcharge:
  └── Per kg after first 0.5kg (provider-specific)
```

### Shipping Quote API

```
POST /api/v1/checkout/quote
Body: { items, delivery_address }
Returns: {
  delivery_options: [
    { provider: 'pathao', charge: 80, estimated_days: 2, type: 'standard' },
    { provider: 'pathao', charge: 150, estimated_days: 1, type: 'express' },
    { provider: 'steadfast', charge: 70, estimated_days: 3, type: 'standard' }
  ]
}
```

---

## Consignment Creation Workflow

```
Admin marks order as 'processing'
          │
          ▼
POST /api/v1/admin/orders/{id}/ship
  { courier: 'pathao', parcel_weight: 0.5 }
          │
          ▼
Validate order is confirmed and unfulfilled
          │
          ▼
Call CourierProvider::createParcel()
          │
          ▼
  ┌───────┴───────┐
 API fails     API succeeds
  │               │
  ▼               ▼
Return 503     Store consignment_id
+ retry advice  in shipments table
               │
               ▼
         Update order fulfilment_status = 'fulfilled'
               │
               ▼
         Emit ShipmentCreated event
               │
               ▼
         Send dispatch notification to customer
```

### Shipments Table

```sql
shipments
├── id
├── order_id
├── courier                -- 'pathao', 'steadfast', 'redx'
├── consignment_id         -- Courier's unique parcel ID
├── tracking_url
├── shipping_charge        -- DECIMAL(12,2)
├── cod_amount             -- DECIMAL(12,2) (if COD)
├── weight_kg              -- DECIMAL(5,2)
├── status                 -- created | in_transit | delivered | failed | returned
├── picked_up_at
├── delivered_at
├── created_at
└── updated_at
```

---

## Tracking Synchronization

### Pull-based Tracking (Scheduled Job)

Runs every 30 minutes via Laravel Scheduler:

```
scheduler: track:shipments
          │
          ▼
Fetch all shipments where status NOT IN ('delivered', 'returned')
          │
          ▼
For each shipment:
  Call CourierProvider::trackParcel(consignment_id)
          │
          ▼
Compare courier status with internal status mapping
          │
          ▼
Update shipment status if changed
          │
          ▼
Record in shipment_tracking_events
          │
          ▼
If delivered:
  Emit OrderDelivered event
    │
    ├── Update order_status = 'delivered'
    └── Send delivery confirmation notification
```

### Courier → Internal Status Mapping

| Pathao Status     | Internal Status   |
|-------------------|-------------------|
| `Pending`         | `created`         |
| `Picked Up`       | `in_transit`      |
| `In Transit`      | `in_transit`      |
| `Delivered`       | `delivered`       |
| `Delivery Failed` | `failed`          |
| `Returned`        | `returned`        |

> **Rule:** Courier status names vary per provider. Map each provider's statuses independently.

### Shipment Tracking Events Table

```sql
shipment_tracking_events
├── id
├── shipment_id
├── courier_status     -- Raw status from courier API
├── internal_status    -- Mapped internal status
├── location
├── notes
├── tracked_at
├── created_at
```

---

## Failed Delivery Handling

```
Courier reports "Delivery Failed"
          │
          ▼
Update shipment.status = 'failed'
          │
          ▼
Record tracking event
          │
          ▼
Notify customer + admin
          │
          ▼
Admin reviews failed delivery:
  ┌────────────┬─────────────┐
Reattempt   Return to     Write-off
delivery    merchant
  │               │
  ▼               ▼
Call courier  Courier initiates RTM
reschedule    (Return to Merchant)
                  │
                  ▼
            Update shipment.status = 'returned'
                  │
                  ▼
            Restore inventory
            (quantity_on_hand += ordered_qty)
                  │
                  ▼
            Admin processes refund if paid online
```

---

## COD Reconciliation

```
Weekly scheduled job: reconcile:cod
          │
          ▼
Fetch delivered COD shipments not yet reconciled
          │
          ▼
Request collection report from courier API
  (or manually upload CSV)
          │
          ▼
Match delivered orders against received payments
          │
          ▼
Mark reconciled orders as paid in financial records
          │
          ▼
Flag discrepancies for admin review
          │
          ▼
Generate reconciliation report
```

---

## Return to Merchant (RTM)

When a parcel is returned:

1. `shipment.status` = `returned`
2. Emit `ParcelReturned` event
3. Inventory module: restore `quantity_on_hand += quantity`
4. Record `inventory_movement` with `type = 'return'`
5. Notify order_manager role
6. Admin manually assesses order for refund/reorder

---

## Shipping Admin API Endpoints

| Method | Endpoint                                    | Purpose                        |
|--------|---------------------------------------------|--------------------------------|
| POST   | `/api/v1/admin/orders/{id}/ship`            | Create courier consignment     |
| GET    | `/api/v1/admin/shipments`                   | All shipments listing          |
| GET    | `/api/v1/admin/shipments/{id}`              | Shipment detail + tracking     |
| POST   | `/api/v1/admin/shipments/{id}/cancel`       | Cancel consignment             |
| GET    | `/api/v1/admin/shipments/cod-reconciliation`| COD reconciliation report      |

## Customer Tracking API

| Method | Endpoint                              | Purpose                  |
|--------|---------------------------------------|--------------------------|
| GET    | `/api/v1/orders/{orderNumber}/tracking` | Order tracking status  |
