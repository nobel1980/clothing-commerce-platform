# Payment Workflow

## Overview

This document defines the payment processing architecture including gateway integration, webhook handling, idempotency, and reconciliation for the Clothing Commerce Platform.

---

## Payment Gateway Architecture

```
Checkout Module
      │
      ▼
PaymentGateway Interface
      │
      ├── BkashGateway (adapter)
      ├── NagadGateway (adapter)
      ├── SslCommerzGateway (adapter)
      └── CodGateway (internal method)
```

### Gateway Contract

```php
interface PaymentGateway
{
    public function initiate(PaymentRequest $request): PaymentInitiationResult;
    public function verify(string $gatewayReference): PaymentVerificationResult;
    public function refund(RefundRequest $request): RefundResult;
}
```

---

## Payment Flow — Online (bKash / Nagad / Card)

```
[Storefront]                [API]                     [Gateway]
      │                       │                            │
      │── POST /checkout/orders ──►│                       │
      │                       │── Create order record      │
      │                       │── POST payment/initiate ──►│
      │                       │◄── payment_url / token ────│
      │◄── payment_url ───────│                            │
      │                       │                            │
      │── Redirect to Gateway ──────────────────────────► │
      │                  Customer completes payment        │
      │◄── Redirect callback ──────────────────────────── │
      │                       │                            │
      │── POST /payments/verify ──►│                       │
      │                       │── Verify server-to-server ►│
      │                       │◄── Verification response ──│
      │                       │── Update order status      │
      │◄── Order confirmation ─│                           │
```

---

## Payment Flow — COD

```
[Storefront]                [API]
      │                       │
      │── POST /checkout/orders ──►│
      │                       │── Create order (payment_status: pending)
      │                       │── order_status: pending_confirmation
      │◄── Order confirmation ─│
                               │
             [Admin confirms COD order]
                               │
                               │── order_status: confirmed
                               │── fulfilment_status: unfulfilled
```

---

## Webhook Processing (Idempotent)

```
[Gateway]                    [API Webhook Handler]             [DB]
    │                                 │                         │
    │── POST /webhooks/bkash ─────────►│                        │
    │                                 │── Check payment_webhook_events │
    │                                 │◄── Already processed? ──│
    │                                 │        │                │
    │                                 │   YES  NO               │
    │                                 │    │    │               │
    │                                 │    │   Verify signature  │
    │                                 │    │    │               │
    │                                 │    │   Update payment_transactions │
    │                                 │    │    │               │
    │                                 │    │   Update order status │
    │                                 │    │    │               │
    │                                 │    │   Record event_id ─►│
    │◄── HTTP 200 ────────────────────│    │    │               │
```

### Idempotency Rules

1. Every incoming webhook must be checked against `payment_webhook_events.gateway_event_id`.
2. If already processed → return `HTTP 200` immediately (no duplicate processing).
3. Verify webhook signatures using the gateway's server-side HMAC/RSA key.
4. **Never trust the callback amount from the browser redirect** — always verify server-to-server.
5. Failed signature verification → return `HTTP 400` and log the attempt.

---

## Payment Transaction Record

```sql
payment_transactions
├── id
├── order_id
├── gateway                    -- 'bkash', 'nagad', 'sslcommerz', 'cod'
├── gateway_transaction_id     -- Unique gateway reference
├── gateway_payment_url        -- Redirect URL (online only)
├── amount                     -- DECIMAL(12,2) in BDT
├── currency                   -- 'BDT'
├── status                     -- pending | paid | failed | refunded
├── raw_initiation_response    -- JSON blob of gateway init response
├── raw_verification_response  -- JSON blob of gateway verify response
├── paid_at
├── failed_at
├── created_at
└── updated_at
```

> **Rule:** All monetary amounts stored as `DECIMAL(12,2)`. Internally calculate in integer poisha (1 BDT = 100 poisha) to avoid floating-point errors.

---

## Inventory Reservation During Payment Window

| Event                             | Inventory Action                       |
|-----------------------------------|----------------------------------------|
| Order placed (online)             | `quantity_reserved += ordered_qty`     |
| Payment confirmed within TTL      | No change (reservation stays)          |
| Payment NOT confirmed within 15m  | `quantity_reserved -= ordered_qty`     |
| Order shipped                     | `quantity_on_hand -= ordered_qty`, `quantity_reserved -= ordered_qty` |

**Reservation expiry job:** Runs every minute via Laravel Scheduler. Selects `payment_transactions` where `status = 'pending'` and `created_at < NOW() - 15 minutes`.

---

## Refund Workflow

```
Admin initiates refund
        │
        ▼
POST /admin/orders/{id}/refund
        │
        ▼
Validate order is in refundable state
(must be paid, not already refunded)
        │
        ▼
Call Gateway::refund()
        │
        ▼
Record refund in payment_transactions
(status: refunded)
        │
        ▼
Update order payment_status = 'refunded'
        │
        ▼
Emit RefundInitiated event → Notification
```

---

## Gateway Configuration

Each gateway adapter reads configuration from environment variables. No gateway credentials may be stored in:
- Source code
- Database
- Docker image layers

```php
// Example: bKash config from environment
config('payment.gateways.bkash.app_key')
config('payment.gateways.bkash.app_secret')
config('payment.gateways.bkash.base_url')
```

---

## Payment API Endpoints

| Method | Endpoint                       | Purpose                             |
|--------|--------------------------------|-------------------------------------|
| POST   | `/api/v1/payments/initiate`    | Start payment session with gateway  |
| POST   | `/api/v1/payments/verify`      | Verify payment server-side          |
| POST   | `/webhooks/bkash`              | bKash IPN/callback receiver         |
| POST   | `/webhooks/nagad`              | Nagad callback receiver             |
| POST   | `/webhooks/sslcommerz`         | SSLCommerz IPN receiver             |

---

## Error Handling

| Scenario                            | Response                            |
|-------------------------------------|-------------------------------------|
| Gateway timeout during initiation   | Return `503`, retry with backoff    |
| Invalid signature on webhook        | Return `400`, log security alert    |
| Duplicate webhook event             | Return `200`, no-op                 |
| Refund gateway failure              | Queue retry, alert admin            |
| Payment amount mismatch             | Mark fraud risk, pause order        |
