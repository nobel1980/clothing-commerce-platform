# API Application Rules (`apps/api/AGENTS.md`)

This directory contains the Laravel 13 modular monolith backend providing REST API endpoints for both the Customer Storefront and Admin Dashboard.

---

## 1. Stack & Standards

* **Framework:** Laravel 13 on PHP 8.3+.
* **Database:** MySQL 8 (InnoDB, strict key checks, proper indexes).
* **Cache & Queues:** Redis with Laravel Queue Workers and Scheduler.
* **Auth & RBAC:** Laravel Sanctum (cookie stateful sessions for web, tokens for external API integration if needed) + Spatie Permission (`spatie/laravel-permission`).
* **Testing:** Pest PHP / PHPUnit.

---

## 2. Domain Module Structure

All core business logic must reside within `app/Modules/`:

```
app/Modules/{ModuleName}/
├── Domain/              # Models, Enums, Events, Exceptions, ValueObjects, Contracts
├── Application/         # Actions, DTOs, Queries, Listeners
├── Infrastructure/      # Repositories, Persistence, External Integrations/Adapters
└── Presentation/        # Http Controllers, Requests (Form Validation), Resources (API Responses)
```

### Module Responsibilities:
* `Identity`: Users, roles, permissions, Sanctum sessions.
* `Catalog`: Products, categories, attributes, variants, media.
* `Inventory`: Warehouses, stock levels, adjustments, reservations.
* `Cart`: Guest/customer shopping carts.
* `Checkout`: Pricing orchestration, discount/tax calculations, quote generation.
* `Orders`: Order state machine, order items, status snapshots.
* `Payments`: Payment transactions, gateway verification adapters, refunds.
* `Promotions`: Coupons, usage caps, campaign rules.
* `Shipping`: Courier adapters (Pathao, Steadfast, REDX), consignment tracking.

---

## 3. Critical Backend Rules

1. **Transactional Outbox for Events:**
   * Every domain event that triggers cross-module side effects or external service execution must write an entry to `outbox_events` within the same database transaction as the business operation.
2. **Inventory Concurrency Guardrail:**
   * All inventory reservations and adjustments MUST execute inside database transactions using pessimistic locking (`SELECT ... FOR UPDATE`).
   * Stock must never fall below zero (`quantity_on_hand - quantity_reserved >= 0`).
3. **API Response Contract:**
   * Every HTTP response must return a standardized JSON structure:
     ```json
     {
       "success": true,
       "message": "Operation response message",
       "data": {},
       "meta": null
     }
     ```
   * Validation errors must strictly return `422 Unprocessable Entity` with an `errors` object.
4. **Idempotency:**
   * Payment webhooks and order placement requests MUST check and write idempotency keys to prevent duplicate execution.