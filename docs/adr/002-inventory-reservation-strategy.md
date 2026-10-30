# ADR 002: Inventory Reservation Strategy & Concurrency

## Context and Problem Statement

During high-traffic events or promotional sales, multiple customers may simultaneously attempt to purchase the same low-stock product variant. We must prevent overselling while avoiding holding uncommitted stock indefinitely when users abandon payment gateway redirects.

## Decision Drivers

* Zero tolerance for overselling stock.
* Support for local MFS payments (bKash/Nagad) where users leave the storefront temporarily to approve payments.
* High performance under concurrent checkout traffic.

## Decision Outcome

**Chosen Option:** **Pessimistic DB Locks (`FOR UPDATE`) with 15-Minute Expiry Engine**.

1. During order placement (`POST /checkout/orders`), lock target rows in `inventory_items` using `SELECT ... FOR UPDATE`.
2. Increment `quantity_reserved` while keeping `quantity_on_hand` intact.
3. Unpaid online orders retain their stock reservation for 15 minutes.
4. An automated worker releases expired reservations if gateway payment verification is not confirmed within the TTL.

### Positive Consequences
* Absolute concurrency protection against overselling.
* Abandoned online payment sessions automatically return stock to available inventory.

### Negative Consequences
* Inventory rows are locked briefly during order placement transactions; transactions must be kept extremely fast and never perform external API HTTP calls while holding locks.