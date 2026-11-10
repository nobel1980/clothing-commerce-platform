# Shipping & Courier Integration API Documentation

The **Shipping Module** (`App\Modules\Shipping`) manages multi-courier delivery charge calculation, consignment creation, live shipment tracking, webhook ingestion, Cash-on-Delivery (COD) collection reconciliation, and returned parcel inventory restoration.

It is decoupled from the `Orders` module via the [`CourierProvider`](file:///d:/wamp/www/clothing-commerce-platform/apps/api/app/Shared/Contracts/Shipping/CourierProvider.php) interface (`App\Shared\Contracts\Shipping\CourierProvider`), ensuring that `Orders` holds zero courier-specific assumptions.

---

## 1. Architecture & Supported Couriers

### `CourierProvider` Interface (`App\Shared\Contracts\Shipping\CourierProvider`)
Every courier adapter implements:
- `getCode(): string` — Canonical courier identifier (`pathao`, `steadfast`, `redx`).
- `getName(): string` — Human-readable display name.
- `calculateDeliveryCharge(DeliveryQuoteRequest $request): DeliveryChargeResult` — Calculates delivery fee and COD surcharge in integer BDT paisa.
- `createShipment(CreateShipmentRequest $request): CreateShipmentResult` — Books a consignment with the courier API.
- `cancelShipment(Shipment $shipment, string $reason): CancelShipmentResult` — Cancels a shipment before pickup.
- `trackShipment(Shipment $shipment): ShipmentTrackingResult` — Queries live tracking status from the courier API.
- `parseTrackingWebhook(array $payload, array $headers, string $rawBody): TrackingWebhookPayload` — Verifies webhook authenticity and normalizes status/amount payloads.
- `mapExternalStatus(string $externalStatus): string` — Maps provider-specific status codes/events to canonical internal statuses.

### Courier Adapters
| Courier | Code | Adapter Class | Merchant API Standard | Status |
| :--- | :--- | :--- | :--- | :--- |
| **Pathao Courier** | `pathao` | [`PathaoCourierAdapter`](file:///d:/wamp/www/clothing-commerce-platform/apps/api/app/Modules/Shipping/Couriers/PathaoCourierAdapter.php) | Pathao Hermes Merchant API v1 (`/aladdin/api/v1/*`, OAuth2 token caching, `X-PATHAO-Signature` verification) | **Primary Full Implementation** |
| **Steadfast Courier** | `steadfast` | [`SteadfastCourierAdapter`](file:///d:/wamp/www/clothing-commerce-platform/apps/api/app/Modules/Shipping/Couriers/SteadfastCourierAdapter.php) | Steadfast Packzy API v1 (`/create_order`, `/status_by_cid/{id}`, `Api-Key`/`Secret-Key` headers) | **Prepared Adapter** |
| **REDX Logistics** | `redx` | [`RedxCourierAdapter`](file:///d:/wamp/www/clothing-commerce-platform/apps/api/app/Modules/Shipping/Couriers/RedxCourierAdapter.php) | REDX Open API `v1.0.0-beta` (`/parcel`, `/parcel/track/{id}`, `API-ACCESS-TOKEN` header) | **Prepared Adapter** |

---

## 2. Canonical Internal Statuses & External Mappings

All external courier statuses are normalized into 9 canonical internal shipment statuses:

| Internal Status | Description | Order Status Transition | Inventory Effect |
| :--- | :--- | :--- | :--- |
| `created` | Consignment booked with courier | Confirmed / Processing -> `shipped` | Reserved / Deducted |
| `picked_up` | Parcel collected from merchant warehouse | `shipped` | None |
| `in_transit` | Moving through courier sorting/hub network | `shipped` | None |
| `out_for_delivery` | Assigned to delivery agent for final mile | `shipped` | None |
| `delivered` | Delivered to recipient (COD collected if applicable) | `shipped` -> `delivered` | None |
| `failed` | Delivery failed or placed on hold | Unchanged | None |
| `returning` | Return initiated; parcel moving back to merchant | Unchanged | None |
| `returned` | Returned parcel received by merchant | `shipped` / `delivered` -> `returned` | **Restores variant stock idempotently** (`type: 'return'`) |
| `cancelled` | Consignment cancelled before pickup | Unchanged | None |

---

## 3. Customer Tracking Endpoint

### `GET /api/orders/{orderNumber}/tracking`
Retrieves shipment consignments and chronological tracking timelines for an order.
- **Authorization**: Requires either an authenticated owner (`user_id` matches order) or a matching `X-Guest-Token` header for guest orders. Staff users with `orders.view` may also access.
- **Success Response (`200 OK`)**:
```json
{
  "data": {
    "order_number": "ORD-20261110-AB12CD",
    "order_status": "shipped",
    "shipments": [
      {
        "id": 1,
        "courier_code": "pathao",
        "consignment_id": "PT-CONS-100200",
        "tracking_number": "PT-CONS-100200",
        "status": "in_transit",
        "shipped_at": "2026-11-10T18:00:00+00:00",
        "delivered_at": null,
        "returned_at": null,
        "events": [
          {
            "internal_status": "in_transit",
            "external_status": "In_Transit",
            "description": "Parcel is in transit to destination hub",
            "location": "Dhaka Hub",
            "occurred_at": "2026-11-10T19:30:00+00:00"
          },
          {
            "internal_status": "picked_up",
            "external_status": "Picked",
            "description": "Parcel picked up by Pathao agent",
            "location": "Gulshan",
            "occurred_at": "2026-11-10T18:15:00+00:00"
          }
        ]
      }
    ]
  }
}
```

---

## 4. Admin Shipment Management Endpoints

All `/api/admin/shipments/*` routes require `auth:sanctum` and Spatie permission `orders.view` (read operations) or `orders.manage` (write operations).

### 4.1 Calculate Delivery Charge
`POST /api/admin/shipments/calculate-charge`
- **Permission**: `orders.view`
- **Request Body**:
```json
{
  "courier_code": "pathao",
  "division": "Dhaka",
  "district": "Dhaka",
  "upazila": "Gulshan",
  "weight_kg": 0.5,
  "cod_amount": 250000,
  "city_id": 1,
  "zone_id": 10
}
```
- **Response (`200 OK`)**:
```json
{
  "data": {
    "courier_code": "pathao",
    "delivery_fee": 7000,
    "cod_fee": 2500,
    "total_fee": 9500,
    "currency": "BDT",
    "estimated_days": 2
  }
}
```

### 4.2 Create Shipment (Idempotent)
`POST /api/admin/shipments`
- **Permission**: `orders.manage`
- **Concurrency & Idempotency**:
  - Uses `idempotency_key` (or defaults to `ship_{order_id}_{courier_code}`).
  - Checks for existing active shipments under row lock before calling external courier HTTP endpoints (**no DB lock held during external HTTP calls**).
  - If a previous shipment creation attempt failed (`status = failed` with no `consignment_id`), retrying with the same idempotency key re-attempts courier booking cleanly.
- **Request Body**:
```json
{
  "order_id": 42,
  "courier_code": "pathao",
  "idempotency_key": "ship-order-42-v1",
  "weight_kg": 0.5,
  "item_description": "Apparel Order ORD-20261110-AB12CD",
  "special_instruction": "Call before delivery",
  "city_id": 1,
  "zone_id": 10,
  "area_id": 105
}
```
- **Response (`201 Created`)**: Returns the created `Shipment` resource with `consignment_id`, `tracking_number`, `delivery_fee`, `cod_amount`, and initial tracking event.

### 4.3 List & Inspect Shipments
- `GET /api/admin/shipments?status=in_transit&courier_code=pathao&cod_reconciliation_status=pending` (`orders.view`)
- `GET /api/admin/shipments/{shipmentId}` (`orders.view`)

### 4.4 Sync Live Tracking from Courier API
`POST /api/admin/shipments/{shipmentId}/track`
- **Permission**: `orders.manage`
- Queries the courier's tracking endpoint, records a new `ShipmentTrackingEvent` if the status changed, and triggers order/inventory transitions if `delivered` or `returned`.

### 4.5 Cancel Shipment
`POST /api/admin/shipments/{shipmentId}/cancel`
- **Permission**: `orders.manage`
- **Request Body**: `{ "reason": "Customer requested address change before pickup" }`
- Only allowed while shipment status is `created`.

### 4.6 COD Collection Reconciliation
- `GET /api/admin/shipments/cod/reconciliation-report` (`orders.view`):
  Returns aggregate counts and integer paisa totals grouped by `(courier_code, cod_reconciliation_status)` (`not_applicable`, `pending`, `reconciled`, `discrepancy`).
- `POST /api/admin/shipments/cod/reconcile` (`orders.manage`):
  Reconciles courier settlement sheets against `cod_amount`:
  ```json
  {
    "settlements": [
      { "consignment_id": "PT-CONS-100200", "collected_amount": 250000 }
    ]
  }
  ```
  Marks each shipment `reconciled` when `collected_amount === cod_amount`, or `discrepancy` when mismatched, and dispatches `CodCollectionReconciled`.

---

## 5. Courier Tracking Webhook Endpoint

### `POST /api/webhooks/shipping/{courier}`
Public webhook endpoint for `pathao`, `steadfast`, and `redx`.
- **Signature Verification**:
  - **Pathao**: Validates `X-PATHAO-Signature` against configured `SHIPPING_PATHAO_WEBHOOK_SECRET` (or HMAC-SHA256 digest).
  - **Steadfast / REDX**: Validates `Authorization: Bearer <secret>` or `X-Webhook-Secret`.
  - Invalid signatures return `401 Unauthorized` and log a redacted entry in `courier_api_logs`.
- **Idempotency**:
  - Computes a deterministic `event_hash` from `(courier_code, consignment_id, external_status, collected_amount)`.
  - Repeated delivery of the same webhook event returns `200 OK` without duplicating tracking events or side effects.
- **Returned Parcel Handling**:
  - When a webhook transitions a shipment to `returned`, `ShipmentService` marks the order as `returned` via `OrderService::markOrderReturned` and restores stock for each order item via `InventoryServiceContract::adjustStock(..., 'return', ...)` guarded by `shipments.inventory_restored_at` so inventory is never restored twice.

---

## 6. Safe API & Error Audit Logging

Every outbound courier HTTP call and inbound webhook is logged in `courier_api_logs` via [`CourierAuditLogger`](file:///d:/wamp/www/clothing-commerce-platform/apps/api/app/Modules/Shipping/Services/CourierAuditLogger.php). All request headers, payloads, and responses pass through [`SensitiveDataRedactor`](file:///d:/wamp/www/clothing-commerce-platform/apps/api/app/Shared/Support/SensitiveDataRedactor.php) to ensure `client_secret`, `password`, `access_token`, `refresh_token`, `api_key`, `secret_key`, and `api-access-token` are redacted as `[REDACTED]` prior to persistence.

