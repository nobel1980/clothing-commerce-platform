# Checkout & Order Placement Workflow Record

## 1. Overview & Core Engineering Principles

Checkout is the primary money-generating workflow of the platform. It is engineered with two distinct stages to guarantee:
1. Authoritative price calculations strictly executed by the backend API.
2. Idempotent order placement preventing duplicate charges and stock overselling.
3. Zero long-lived open database transactions during third-party gateway payment calls.

---

## 2. Two-Stage Checkout Architecture

```
[Storefront UI]                         [Checkout API]                   [Inventory / MySQL DB]
       |                                      |                                     |
       |----- 1. POST /checkout/quote ------->|                                     |
       |      (Cart items & address)          |-- Validates variants & stock ------>|
       |                                      |-- Calculates taxes, coupon, fee --->|
       |<---- 2. Returns Checkout Quote ------|                                     |
       |      (With quote_id & TTL)           |                                     |
       |                                      |                                     |
       |----- 3. POST /checkout/orders ------>|                                     |
       |      (quote_id, idempotency_key)     |-- BEGIN DB TRANSACTION ------------>|
       |                                      |-- Lock Inventory (FOR UPDATE) ------>|
       |                                      |-- Reserve Stock ------------------->|
       |                                      |-- Create Order & Order Items ------>|
       |                                      |-- Save Outbox Event --------------->|
       |                                      |-- COMMIT DB TRANSACTION ------------|
       |                                      |                                     |
       |                                      |-- If Online Payment (bKash/Nagad) ->|
       |<---- 4. Order Placed / Payment URL --|   (Initiate API call to Gateway)    |
```

---

## 3. Detailed Stage Execution

### Stage 1: Checkout Quote (`POST /checkout/quote`)
* **Purpose:** Calculates pricing without locking inventory or modifying database state.
* **Steps:**
  1. Validate cart variant IDs and check current available stock ($\text{quantity\_on\_hand} - \text{quantity\_reserved}$).
  2. Execute pricing engine calculation:
     $$\text{Order Total} = \text{Variant Base Prices} - \text{Promotional Discounts} - \text{Coupon Savings} + \text{Shipping Fee} + \text{Tax}$$
  3. Issue a `checkout_quote` payload signed with an expiration timestamp (TTL: 15 minutes).

### Stage 2: Order Placement (`POST /checkout/orders`)
* **Purpose:** Transactional lock and creation of the persistent order.
* **Steps:**
  1. Validate quote freshness and `idempotency_key`.
  2. **Begin Database Transaction.**
  3. Acquire pessimistic locks on affected `inventory_items` rows (`SELECT ... FOR UPDATE`).
  4. Confirm available stock is sufficient for all requested quantities.
  5. Reserve inventory quantity (`quantity_reserved += line_quantity`).
  6. Insert `orders` and `order_items` snapshot records.
  7. Write `OrderPlaced` domain event to `outbox_events`.
  8. **Commit Database Transaction.**
  9. If payment is COD, mark order as pending confirmation. If payment is online MFS (bKash/Nagad), initiate gateway session and return redirect URL.

---

## 4. Payment Verification & Stock Expiry Rules

* **Reservation Timeout Worker:** A background scheduled worker polls for expired reservations (`created_at < NOW() - 15 minutes` for unpaid online orders) and releases `quantity_reserved`.
* **Idempotent Webhooks:** All incoming payment webhooks MUST verify signatures server-to-server and log processed event IDs to `payment_webhook_events` to ignore duplicate callbacks.