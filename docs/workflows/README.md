# Workflows Index

This directory contains business workflow documentation for all critical processes in the Clothing Commerce Platform.

---

## Workflow Documents

| Document | Domain | Description |
|---|---|---|
| [Planning.md](./Planning.md) | All | Master software architecture blueprint (2,300+ lines) |
| [checkout_workflow.md](./checkout_workflow.md) | Checkout | Two-stage checkout, inventory locking, idempotency |
| [auth_and_authorization_workflow.md](./auth_and_authorization_workflow.md) | Identity | Registration, login, RBAC, CSRF, audit logging |
| [product_publishing_workflow.md](./product_publishing_workflow.md) | Catalog | Product creation, validation, publish, cache invalidation |
| [order_lifecycle_workflow.md](./order_lifecycle_workflow.md) | Orders | Order status machine, snapshots, cancellation rules |
| [payment_workflow.md](./payment_workflow.md) | Payments | Gateway adapters, webhooks, idempotency, refunds |
| [inventory_management_workflow.md](./inventory_management_workflow.md) | Inventory | Stock adjustments, reservations, movements, low-stock |
| [courier_shipping_workflow.md](./courier_shipping_workflow.md) | Shipping | Pathao/Steadfast/REDX, consignment, tracking, COD |
| [promotions_workflow.md](./promotions_workflow.md) | Promotions | Coupons, discount types, pricing engine, anti-abuse |

---

## Cross-Workflow Dependencies

```
Auth (Identity)
    │
    ├── guards all customer APIs (Sanctum session)
    └── guards all admin APIs (Sanctum + RBAC)

Product Publishing
    │
    ├── requires Catalog module (categories, variants, images)
    └── triggers cache invalidation → Storefront

Checkout
    ├── reads Catalog (variant validation + prices)
    ├── reads Promotions (coupon validation)
    ├── writes Inventory (stock reservation)
    ├── writes Orders (order + items snapshots)
    └── triggers Payment (gateway initiation)

Payment
    ├── reads Orders (order state)
    ├── writes Orders (payment_status update)
    └── releases Inventory (on failure/expiry)

Order Lifecycle
    ├── triggers Inventory (fulfilment stock deduction)
    ├── triggers Shipping (consignment creation)
    └── triggers Notifications (status updates)

Shipping
    ├── reads Orders (fulfilment dispatch)
    ├── writes Inventory (RTM stock restore)
    └── triggers Notifications (delivery updates)

Promotions
    └── integrated at Checkout stage (coupon validation + application)
```

---

## Implementation Phase Mapping

| Phase | Workflows Involved |
|---|---|
| Phase 1 — Foundation | Infrastructure, Docker, CI |
| Phase 2 — Identity | Auth & Authorization |
| Phase 3 — Catalog | Product Publishing |
| Phase 4 — Inventory | Inventory Management |
| Phase 5 — Storefront | (Catalog read APIs + Next.js UI) |
| Phase 6 — Cart & Checkout | Checkout |
| Phase 7 — Orders & Payments | Order Lifecycle, Payment |
| Phase 8 — Admin Operations | All admin views |
| Phase 9 — Courier | Courier & Shipping |
| Phase 10 — Promotions | Promotions |
| Phase 11 — QA & Security | All workflows (testing) |
| Phase 12 — Production Launch | All workflows (monitoring) |
