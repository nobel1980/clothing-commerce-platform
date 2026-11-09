# Clothing Commerce Platform

A modern, high-performance, modular clothing e-commerce platform built as an enterprise-grade monorepo featuring a **Next.js 16** frontend (Storefront & Admin Backoffice), a domain-modular **Laravel 13** REST API on **PHP 8.3+**, **MySQL 8**, **Redis 7**, **NGINX**, and containerized orchestration. Designed for regional scalability and localized commerce with bKash, Nagad, Cash On Delivery (COD), Pathao, Steadfast, and REDX courier integrations.

---

## Architecture Overview

```
clothing-commerce-platform/
│
├── apps/
│   ├── web/                     # Next.js 16 App Router (Turbopack, React 19, Strict TS)
│   │   ├── src/app/(store)/     # Customer-facing storefront routes (✅ Implemented)
│   │   ├── src/app/(admin)/     # Backoffice administrative portal routes
│   │   ├── src/app/api/health/  # Frontend healthcheck route
│   │   ├── src/app/api/revalidate/ # Tag & path cache invalidation webhook (✅ Implemented)
│   │   ├── src/features/        # Domain-driven features (catalog, cart, checkout, orders, customers, admin)
│   │   ├── src/components/      # Reusable shadcn/ui primitives, shared states & layouts
│   │   └── package.json
│   │
│   └── api/                     # Laravel 13 Modular Architecture (PHP 8.3+)
│       ├── app/Modules/         # 13 Isolated business domains
│       │   ├── Identity/        # Authentication, RBAC, Users, Addresses, Audit Logs (✅ Implemented)
│       │   ├── Catalog/         # Categories, Products, Variants, Images, Attributes (✅ Implemented)
│       │   ├── Inventory/       # Warehouses, Stock items, Movements, Reservations, Reports (✅ Implemented)
│       │   ├── Cart/            # Guest & Customer carts, Merging, Address & Coupon binding (✅ Implemented)
│       │   ├── Checkout/        # Two-stage checkout quotes, Price/Stock revalidation, Order placement (✅ Implemented)
│       │   ├── Orders/          # Order & OrderItem immutable snapshots, OrderPolicy (✅ Implemented)
│       │   ├── Promotions/      # Coupons, Redemptions, Expiry & usage limit validation (✅ Implemented)
│       │   ├── Shipping/        # Bangladesh Division/District shipping calculation & courier adapters (✅ Implemented)
│       │   ├── Payments/        # Gateway adapters (bKash, Nagad, COD), refunds, webhooks
│       │   ├── Customers/       # Customer profiles, wishlists, address books
│       │   ├── Reviews/         # Ratings, reviews & moderation
│       │   ├── Notifications/   # Asynchronous transactional email & SMS
│       │   └── Reporting/       # Analytics, sales aggregation, read models
│       ├── app/Shared/          # Contracts, Events, Exceptions, Money value objects, BD Validators
│       ├── app/Jobs/            # Asynchronous Queue Jobs
│       ├── routes/              # api.php, console.php (scheduler), web.php
│       └── composer.json
│
├── packages/                    # Shared TypeScript packages
│   ├── ui/                      # Shared design system components (Button, Badge, Card, StatusIndicator)
│   ├── types/                   # Cross-cutting TypeScript contracts & API models
│   ├── eslint-config/           # Shared linting configs (base & next)
│   └── tsconfig/                # Shared compiler configs (base, nextjs, react-library)
│
├── infrastructure/              # Containerization, Reverse Proxy & Storage
│   ├── docker/                  # Dockerfiles (Dockerfile.web, Dockerfile.api, Dockerfile.nginx)
│   ├── supervisor/              # Supervisord & queue worker daemon configs
│   ├── nginx/                   # Reverse proxy gateway, SSL termination & rate limiting
│   ├── mysql/                   # MySQL 8 custom my.cnf & 01-init.sql
│   └── redis/                   # Redis 7 production redis.conf
│
├── docs/                        # Architecture, ADRs, database diagrams & workflows
│   ├── architecture/            # System, backend, frontend & security architecture
│   ├── database/                # Database design & ERD
│   ├── api/                     # API conventions & Cart/Checkout API contracts
│   ├── workflows/               # Business process workflows (checkout, orders, payments, etc.)
│   ├── security/                # Security architecture & threat model
│   ├── deployment/              # Deployment guide & runbooks
│   └── adr/                     # Architecture Decision Records
│
├── scripts/                     # Local validation & database bootstrap scripts
├── .github/workflows/           # GitHub Actions CI pipeline (lint, typecheck, tests, docker)
├── docker-compose.yml           # Local development Docker Compose stack
├── docker-compose.prod.yml      # Multi-replica production Docker Compose stack
├── pnpm-workspace.yaml          # Monorepo workspace configuration
├── package.json                 # Monorepo root scripts & dev dependencies
├── AGENTS.md                    # Coding & architectural guidelines for contributors
├── .env.example                 # Baseline environment configuration template
├── .env.development.example     # Local development template
├── .env.production.example      # Production deployment template
└── README.md
```

---

## Tech Stack & Core Versions

| Layer | Technology | Version | Purpose |
| :--- | :--- | :--- | :--- |
| **Frontend** | Next.js (App Router) | `16.x` | Server components, static optimization & storefront/admin |
| **Language** | TypeScript (Strict) | `5.6+` | Type safety across `web`, `types`, and `ui` packages |
| **Backend** | Laravel Framework | `13.x` | Domain-modular REST API, queue processing & scheduling |
| **Runtime** | PHP CLI / FPM | `8.3+` | High performance PHP engine with opcache & JIT |
| **Database** | MySQL | `8.0 / 8.3` | Relational storage with persistent volumes & UTF8mb4 |
| **Cache/Queue** | Redis | `7.x` | Queue broker, session driver, and distributed caching |
| **Gateway** | NGINX | `1.27 Alpine` | Reverse proxy, SSL termination, static serving & rate limiting |
| **Auth** | Laravel Sanctum | `4.x` | Cookie-based SPA & bearer token API authentication |
| **RBAC** | Spatie Permission | `6.x` | Role-based access control for administrative staff |
| **Package Mgr** | pnpm | `9.x` | Fast, deterministic monorepo dependency management |

---

## Domain Modules & Implementation Status

The backend is built around a **Modular Monolith** pattern inside `apps/api/app/Modules/`. Each domain encapsulates its own Models, Controllers, Services, Form Requests, API Resources, and Migrations:

| Module | Status | Entities & Capabilities | API Endpoints |
| :--- | :---: | :--- | :--- |
| **Identity** | ✅ Complete | Users, Roles, Permissions, Bangladesh Addresses, Audit Logs, Staff Authentication | `/api/auth/*`<br>`/api/account/*`<br>`/api/admin/auth/*` |
| **Catalog** | ✅ Complete | Brands, Categories, Attributes, AttributeValues, Products, Variants, Images, Bulk Import, Search & Filtering | `/api/catalog/*`<br>`/api/admin/catalog/*` |
| **Inventory** | ✅ Complete | Warehouses, InventoryItems, InventoryMovements, StockReservations, StockReservationItems, Low-Stock Alerts, Reporting | `/api/inventory/*`<br>`/api/admin/inventory/*` |
| **Cart** | ✅ Complete | Guest & Customer carts, Idempotent cart merging, Item CRUD, Address & Coupon binding | `/api/cart/*` |
| **Checkout** | ✅ Complete | Two-stage checkout quotes (`quote_token`), Price/Stock revalidation, Row locking, Idempotent order placement | `/api/checkout/*` |
| **Orders** | ✅ Complete | Immutable `Order` & `OrderItem` snapshots, COD & Online payment preparation, `OrderPolicy` | `/api/account/orders`<br>`/api/checkout/orders/*` |
| **Promotions** | ✅ Complete | Coupons (`percentage` & `fixed_amount`), Redemptions, Expiry & usage limit validation | `/api/cart/coupon`<br>`/api/checkout/validate-coupon` |
| **Shipping** | ✅ Complete | Bangladesh 8-Division / District shipping calculation (Dhaka Metro, Sub-Area, Outside Dhaka, Pickup) | `/api/checkout/shipping-rates` |
| **Payments** | ⏳ Next Up | Gateway webhook verification & settlement adapters (bKash, Nagad, SSLCommerz) | `/api/payments/*`<br>`/api/webhooks/*` |
| **Customers** | ✅ Complete | Customer profile management, Bangladesh address book, order history | `/api/account/*` |
| **Reviews** | 📋 Planned | Verified buyer reviews, star ratings, moderation workflows | `/api/reviews/*` |
| **Notifications** | 📋 Planned | Asynchronous transactional emails, SMS alerts, low-stock alerts | Internal Queue |
| **Reporting** | 📋 Planned | Sales summaries, revenue trends, inventory velocity read-models | `/api/admin/reports/*` |

---

## Implemented Modules Deep Dive

### 1. Identity Module (`app/Modules/Identity`)
- **Authentication**: Laravel Sanctum stateful cookies for SPA and tokens for API clients.
- **Role-Based Access Control**: Managed via `spatie/laravel-permission` with seeders and migration guarantees.
- **Bangladesh Address Management**: Multi-address management (`division`, `district`, `upazila_thana`, `street_address`, `postal_code`, `phone`) with default flag protection.
- **Audit Logging**: Asynchronous audit recording for security and administrative operations.

### 2. Catalog Module (`app/Modules/Catalog`)
- **Entities**:
  - `Brand`: Name, slug, website, logo URL, active status.
  - `Category`: Self-referencing hierarchical tree (`parent_id`), slug, description, image, order column, active status.
  - `Attribute` & `AttributeValue`: Dynamic product traits (e.g., Color, Size, Material).
  - `Product`: Name, unique slug, description, Brand relationship, Category relationship, SEO title & description, publication status (`draft`, `published`, `archived`), publication timestamp, and featured flag.
  - `ProductVariant`: SKU (unique), color, size, selling price, compare-at price, cost price, and status (`active`, `inactive`).
  - `ProductImage`: Storage disk abstraction (`disk`, `path`), URL accessor, display order (`sort_order`), and primary flag (`is_primary`).
  - `VariantAttributeValue`: Pivot associating variants with dynamic attribute values.
- **Architectural Rules Enforced**:
  - **Financial Precision**: All prices (`price_amount`, `compare_at_price`, `cost_price`) are strictly validated and stored as positive **integers** in smallest currency subunits (paisa). Floating-point arithmetic is prohibited.
  - **Zero Public Leaks**: Public storefront routes enforce strict `published()` query scopes. Drafts, archived items, future-dated products, and inactive categories return HTTP 404 to unauthenticated store visitors.
  - **Storage Abstraction**: Product image uploads utilize Laravel's `Storage` facade (`disk` + `path`). Images are never tied to hardcoded local file paths.
  - **Category Tree Integrity**: Circular parenting prevention and orphan protection on category deletion (`parent_id = null`).
  - **Bulk Import Foundation**: Extensible CSV/JSON product and variant importer (`ImportService`) executed within database transactions.

### 3. Inventory Module (`app/Modules/Inventory`)
- **Entities**:
  - `Warehouse`: Multi-warehouse support with priority ordering, active status, and default fulfillment routing.
  - `InventoryItem`: Per-variant, per-warehouse stock balances (`quantity_on_hand`, `quantity_reserved`, `low_stock_threshold`) where $\text{available stock} = \text{quantity\_on\_hand} - \text{quantity\_reserved}$.
  - `InventoryMovement`: Immutable ledger of every stock modification (`adjustment`, `restock`, `sale`, `reservation`, `release`, `expiry`, `transfer_in`, `transfer_out`).
  - `StockReservation` & `StockReservationItem`: Idempotent, TTL-bound stock reservations across warehouses.
- **Architectural Rules Enforced**:
  - **Never Negative Stock**: Pessimistic row-level locking (`SELECT ... FOR UPDATE`) in deterministic ascending ID order inside `DB::transaction` prevents overselling and deadlocks under concurrent load.
  - **Module Isolation**: External modules (`Catalog`, `Cart`, `Checkout`, `Orders`) are strictly forbidden from mutating inventory tables directly and must communicate through `App\Shared\Contracts\Inventory\InventoryServiceContract` (`InventoryWriteGuard` enforces runtime isolation).
  - **Scheduled Expiry**: `php artisan inventory:expire-reservations` runs every minute via the Laravel scheduler to release overdue reservations and record ledger movements.
  - **Low-Stock Notifications**: Queued `SendLowStockAlertJob` and `LowStockAlertNotification` dispatch alerts when available stock crosses `low_stock_threshold`.

### 4. Cart & Checkout Modules (`app/Modules/Cart`, `app/Modules/Checkout`, `app/Modules/Orders`, `app/Modules/Promotions`, `app/Modules/Shipping`)
- **Capabilities**:
  - **Guest & Customer Carts**: Identified via `X-Guest-Token` / `guest_token` or authenticated `user_id`.
  - **Idempotent Cart Merging**: `POST /api/cart/merge` merges guest cart items, coupon, and address into the customer's cart under row locks without duplicating quantities on retry.
  - **Bangladesh Localization**: Enforces `BDT` (`৳`) currency, Bangladesh mobile phone validation (`^(?:\+?8801|01)[3-9]\d{8}$`), and 8 Bangladesh Divisions (`Dhaka`, `Chattogram`, `Rajshahi`, `Khulna`, `Barishal`, `Sylhet`, `Rangpur`, `Mymensingh`), `district`, and `upazila_thana`.
  - **Coupon & Shipping Calculation**: Validates percentage and fixed-amount coupons (`CouponService`), expiry timestamps, usage limits, and minimum order amounts; computes zone-based Bangladesh shipping rates (`ShippingCalculationService`).
  - **Two-Stage Checkout (`CheckoutQuote` & `Order`)**:
    1. `POST /api/checkout/quote` revalidates variants and stock, calculates authoritative BDT totals in integer paisa (`Money`), and issues a 15-minute `quote_token`.
    2. `POST /api/checkout/orders` enforces idempotency (`Idempotency-Key`), detects variant price drift (`PriceChangedException`, HTTP `409`) and expired quotes (`QuoteExpiredException`, HTTP `422`), locks inventory rows via `InventoryServiceContract::reserveStock`, persists immutable `Order` and `OrderItem` snapshots, and prepares COD or online payment metadata (`ready_for_gateway`) while never holding DB locks during external callbacks.

### 5. Next.js 16 Customer Storefront (`apps/web`)
- **Pages (`src/app/(store)`)**:
  - Homepage (`/`), Category Index (`/categories`) & Category Detail (`/categories/[slug]`), Product Listing (`/products`), Product Details (`/products/[slug]`), Search (`/search`), Cart (`/cart`), Checkout (`/checkout`), Customer Account (`/account`, `/login`, `/register`), and Order History (`/account/orders`, `/orders`).
- **Architectural Rules Enforced**:
  - **Server Components by Default**: Public catalog pages render as React Server Components with dynamic `generateMetadata`, canonical URLs, and Schema.org `Product` + `AggregateOffer` JSON-LD structured data.
  - **Zero Client Price Calculation**: Cart and Checkout views never compute authoritative totals on the client; all prices, discounts, shipping fees, and 5% VAT come from `/api/checkout/quote`.
  - **Customer Data Privacy & Cache Invalidation**: Private routes (`/account`, `/orders`, `/cart`, `/checkout`) enforce `export const dynamic = 'force-dynamic'` and `cache: 'no-store'`, while public catalog caches are invalidated via `POST /api/revalidate`.

---

## API Reference

### 1. Catalog API (`/api/catalog/*` & `/api/admin/catalog/*`)

| Method | Endpoint | Description | Access |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/catalog/categories` | List active root categories with active children | Public |
| `GET` | `/api/catalog/categories/{slug}` | Get category details with active descendants | Public |
| `GET` | `/api/catalog/products` | Paginated product listing (`search`, `category`, `brand`, `color`, `size`, `sort`) | Public |
| `GET` | `/api/catalog/products/{slug}` | Get published product with brand, category, images, and active variants | Public |
| `GET/POST` | `/api/admin/catalog/categories` | List / create categories (`GET /tree` for full hierarchy) | Staff |
| `GET/POST/PUT/DELETE` | `/api/admin/catalog/products/{id}` | Full product, variant, image, publish/unpublish & bulk import management | Staff |

### 2. Inventory API (`/api/inventory/*` & `/api/admin/inventory/*`)

| Method | Endpoint | Description | Access |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/inventory/variants/{variant}/availability` | Live stock summary (`in_stock`, `is_low_stock`, `total_available`) | Public |
| `GET/POST/PUT/DELETE` | `/api/admin/inventory/warehouses` | Manage fulfillment warehouses | Staff |
| `GET/PUT` | `/api/admin/inventory/items` | Inspect and configure inventory item thresholds | Staff |
| `POST` | `/api/admin/inventory/adjust` | Transactional stock adjustment with ledger movement | Staff |
| `POST` | `/api/admin/inventory/set-stock` | Set exact on-hand quantity with audit movement | Staff |
| `POST` | `/api/admin/inventory/transfer` | Atomic inter-warehouse stock transfer | Staff |
| `GET` | `/api/admin/inventory/movements` | Immutable stock movement history ledger | Staff |
| `GET/POST` | `/api/admin/inventory/reservations` | Create, list, commit, release, or expire stock reservations | Staff |
| `GET` | `/api/admin/inventory/reports/{summary\|low-stock\|warehouses\|movements}` | Inventory analytics and low-stock reporting | Staff |

### 3. Cart & Checkout API (`/api/cart/*` & `/api/checkout/*`)

| Method | Endpoint | Description | Access |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/cart` | Retrieve active customer or guest cart (`X-Guest-Token`) | Public / Auth |
| `POST` | `/api/cart/items` | Add variant to cart with stock & variant revalidation | Public / Auth |
| `PUT/PATCH/DELETE` | `/api/cart/items/{item}` | Update item quantity or remove item from cart | Public / Auth |
| `PUT/POST` | `/api/cart/address` | Select saved `address_id` or set Bangladesh `shipping_address` | Public / Auth |
| `POST/DELETE` | `/api/cart/coupon` | Apply or remove promotional coupon code | Public / Auth |
| `POST` | `/api/cart/merge` | Idempotently merge guest cart into authenticated customer cart | Auth (`sanctum`) |
| `POST` | `/api/checkout/quote` | Generate authoritative BDT `CheckoutQuote` (`quote_token`, 15m TTL) | Public / Auth |
| `POST` | `/api/checkout/validate-coupon` | Validate coupon code against subtotal in BDT paisa | Public / Auth |
| `POST` | `/api/checkout/shipping-rates` | Calculate BDT shipping fees for Bangladesh `division` & `district` | Public / Auth |
| `POST` | `/api/checkout/orders` | Idempotent order placement (`Idempotency-Key`, COD or Online prep) | Public / Auth |
| `GET` | `/api/checkout/orders/{orderNumber}` | Retrieve order snapshot (protected by `OrderPolicy`) | Owner / Staff |
| `GET` | `/api/account/orders` | List authenticated customer's order history | Auth (`sanctum`) |

---

## Admin Roles & Permissions (RBAC)

| Role | Permissions & Responsibilities |
| :--- | :--- |
| `super_admin` | Full platform control, user management, configuration, audit trail review |
| `product_manager` | Catalog management, category tree, product variants, media assets, publishing |
| `inventory_manager` | Stock allocation, warehouse transfers, ledger corrections, safety buffer rules |
| `order_manager` | Order lifecycle fulfillment, address corrections, cancellations, dispatch |
| `customer_support` | Read-only order and customer view, support dispute inspection |
| `marketing_manager` | Coupons, campaign rules, promotional banners, discount thresholds |
| `report_viewer` | Read-only access to sales performance, profit margins, and inventory health |

---

## Prerequisites

- **Node.js**: `>= 20.x` (Recommended: v22 LTS or v24)
- **pnpm**: `>= 9.x` (`corepack enable && corepack prepare pnpm@9.0.0 --activate`)
- **PHP**: `>= 8.3` with extensions: `pdo_mysql`, `bcmath`, `opcache`, `intl`, `mbstring`, `zip`, `xml`
- **Composer**: `>= 2.7`
- **Docker & Docker Compose**: (Recommended for local containerized orchestration)

---

## Quick Start Guide

### Option A: Local Development with Docker Compose (Recommended)

1. **Configure Environment**:
   ```bash
   cp .env.development.example .env
   ```

2. **Start All Services**:
   ```bash
   docker compose up --build
   ```

3. **Run Migrations & Seeders (first time)**:
   ```bash
   docker compose exec api php artisan migrate --seed
   ```

4. **Verify Running Services**:
   - Web Storefront: [http://localhost:3000](http://localhost:3000)
   - NGINX Gateway: [http://localhost](http://localhost)
   - Laravel API: [http://localhost:8000](http://localhost:8000)
   - Web Healthcheck: [http://localhost:3000/api/health](http://localhost:3000/api/health)
   - API Healthcheck: [http://localhost/api/health](http://localhost/api/health)

---

### Option B: Local Development on Host Machine

1. **Install Monorepo Node Dependencies**:
   ```bash
   pnpm install
   ```

2. **Install Laravel API Dependencies & Configure DB**:
   ```bash
   cd apps/api
   composer install
   cp .env.example .env
   php artisan key:generate
   php artisan migrate --seed
   ```

3. **Run Next.js 16 Web Application**:
   ```bash
   # From root directory
   pnpm dev
   ```

4. **Run Laravel 13 API Server**:
   ```bash
   cd apps/api
   php artisan serve --port=8000
   ```

---

## Testing & Quality Validation

Run comprehensive verification across both the frontend monorepo packages and the Laravel backend:

### Backend Tests (PHPUnit)
```bash
cd apps/api

# Run all unit and feature tests (Identity, Catalog, Inventory, Cart & Checkout)
php artisan test

# Run specific domain test suites
php artisan test --filter=Inventory
php artisan test --filter=CartAndCheckoutTest
php artisan test --filter=CheckoutDomainUnitTest
php artisan test --filter=Catalog

# Verify queue job dispatch and execution
php artisan test:dispatch-job
php artisan queue:work --once

# Verify scheduler run (including inventory:expire-reservations)
php artisan schedule:run
```

### Frontend Typechecking & Builds
```bash
# Verify shared types package
pnpm --filter @clothing-commerce/types typecheck

# Verify shared UI library
pnpm --filter @clothing-commerce/ui typecheck

# Verify Next.js web application
pnpm --filter web typecheck
pnpm --filter web build
```

---

## Health Check Endpoints

| Service | Endpoint | Description | Sample Response |
| :--- | :--- | :--- | :--- |
| **Next.js 16** | `GET /api/health` | Web application uptime and container probe | `{"status":"healthy","service":"clothing-commerce-web","uptimeSeconds":142}` |
| **Laravel 13** | `GET /api/health` | Tests active MySQL connection, Redis ping, and queue broker | `{"status":"healthy","checks":{"database":{"status":"ok"},"redis":{"status":"ok"},"queue":{"status":"ok"}}}` |
| **NGINX** | `GET /nginx-health` | High-frequency status probe for load balancers | `healthy` |

---

## Background Workers & Scheduling

### Queue Worker
Heavy workflows (image processing, catalog sync, low-stock notifications, invoice generation) implement `Illuminate\Contracts\Queue\ShouldQueue`:

```bash
cd apps/api

# Run worker in daemon mode with resilience parameters
php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
```

In Docker environments, workers are managed automatically by the `queue` service via Supervisor (`infrastructure/supervisor/supervisord.conf`).

### Scheduler
Scheduled tasks are registered in `apps/api/routes/console.php` (including `inventory:expire-reservations`):

```bash
# List all active schedules
php artisan schedule:list

# Trigger pending tasks manually
php artisan schedule:run
```

---

## Production Deployment

Production uses `docker-compose.prod.yml` featuring:
- Hardened non-root containers (`nextjs:nodejs` uid `1001` and `www-data`)
- Multi-stage Docker builds with standalone Next.js output
- Persistent named volumes (`mysql_data_prod`, `redis_data_prod`, `api_storage_prod`)
- Horizontal replica scaling for `web` and `api` services
- Explicit memory and CPU resource reservations & limits

```bash
cp .env.production.example .env.production
docker compose -f docker-compose.prod.yml up -d --build
```

Consult [docs/deployment/deployment-guide.md](./docs/deployment/deployment-guide.md) for complete production zero-downtime procedures.

---

## Continuous Integration (CI)

The GitHub Actions pipeline at `.github/workflows/ci.yml` validates every pull request:
1. **Frontend Job**: Dependency integrity verification, strict TypeScript check on `@clothing-commerce/types`, `@clothing-commerce/ui`, and `apps/web`, followed by Next.js production build.
2. **Backend Job**: PHP 8.3 test matrix running with live MySQL 8 and Redis 7 containers, running migrations, seeders, feature tests, and queue verification.
3. **Docker Job**: Configuration syntax validation and compose file linting.

---

## Documentation Index

| Document | Description |
| :--- | :--- |
| [AGENTS.md](./AGENTS.md) | Architectural invariants, security policies, and contribution guidelines |
| [docs/api/cart_and_checkout_api.md](./docs/api/cart_and_checkout_api.md) | Cart, Checkout Quote, Bangladesh Shipping, Coupon, and Order Placement API contracts |
| [docs/api/api_conventions.md](./docs/api/api_conventions.md) | REST API design conventions, status codes, and error payloads |
| [docs/workflows/Planning.md](./docs/workflows/Planning.md) | Comprehensive system architecture and domain blueprint |
| [docs/workflows/README.md](./docs/workflows/README.md) | Workflow directory and domain dependency map |
| [docs/workflows/product_publishing_workflow.md](./docs/workflows/product_publishing_workflow.md) | Product lifecycle, variant validation, and cache invalidation rules |
| [docs/workflows/checkout_workflow.md](./docs/workflows/checkout_workflow.md) | Two-stage checkout, stock reservation lock, and idempotency guarantees |
| [docs/workflows/inventory_management_workflow.md](./docs/workflows/inventory_management_workflow.md) | Multi-warehouse stock tracking, reservations, and ledger movements |
| [docs/workflows/order_lifecycle_workflow.md](./docs/workflows/order_lifecycle_workflow.md) | Order state machine, line-item snapshots, and fulfillment transitions |
| [docs/workflows/payment_workflow.md](./docs/workflows/payment_workflow.md) | Payment gateway adapters (bKash, Nagad, COD), webhooks, and refunds |
| [docs/workflows/courier_shipping_workflow.md](./docs/workflows/courier_shipping_workflow.md) | Courier integrations (Pathao, Steadfast, REDX), COD tracking, consignments |
| [docs/workflows/auth_and_authorization_workflow.md](./docs/workflows/auth_and_authorization_workflow.md) | Identity authentication, Sanctum session management, and RBAC matrix |
| [docs/workflows/promotions_workflow.md](./docs/workflows/promotions_workflow.md) | Promotion engine, coupon validation rules, and anti-abuse safeguards |
| [docs/architecture/system_architecture.md](./docs/architecture/system_architecture.md) | Overall system architecture and infrastructure topologies |
| [docs/architecture/backend_architecture.md](./docs/architecture/backend_architecture.md) | Laravel modular monolith structure and conventions |
| [docs/architecture/frontend_architecture.md](./docs/architecture/frontend_architecture.md) | Next.js 16 App Router layout, state management, and RSC design |
| [docs/database/database-design.md](./docs/database/database-design.md) | Relational database schema, ERD, and indexing strategies |
| [docs/security/security-architecture.md](./docs/security/security-architecture.md) | Security framework, threat model, and defense-in-depth policies |
| [docs/deployment/deployment-guide.md](./docs/deployment/deployment-guide.md) | Production deployment, container setup, and rollback instructions |
| [docs/adr/](./docs/adr/) | Architectural Decision Records |

---

## Implementation Roadmap

| Phase | Milestone | Focus Area | Status |
| :---: | :--- | :--- | :---: |
| **01** | Foundation | Monorepo setup, Docker environments, Next.js 16, Laravel 13, CI | ✅ Complete |
| **02** | Identity & Auth | Sanctum auth, Spatie RBAC, user profiles, Bangladesh address book, audit logs | ✅ Complete |
| **03** | Catalog Management | Brands, categories, attributes, products, variants, images, search/filtering, bulk import | ✅ Complete |
| **04** | Inventory Management | Multi-warehouse stock balances, movements ledger, idempotent reservations, scheduled expiry, low-stock alerts | ✅ Complete |
| **05** | Storefront Portal | Next.js 16 RSC storefront, category & product pages, image gallery, live stock selector, SEO & JSON-LD | ✅ Complete |
| **06** | Cart & Checkout | Guest & customer carts, cart merging, Bangladesh shipping/phone validation, coupons, quotes, idempotent orders | ✅ Complete |
| **07** | Orders & Payments | Order fulfillment state machine, bKash / Nagad / SSLCommerz webhook verification | ⏳ Next Up |
| **08** | Admin Operations | Backoffice catalog management, inventory adjustments, order fulfillment | 🔲 Planned |
| **09** | Courier Integration | Pathao, Steadfast, REDX automated consignments, tracking, COD reconciliation | 🔲 Planned |
| **10** | Promotions & Reviews | Customer product reviews, ratings moderation, wishlists | 🔲 Planned |
| **11** | Quality & Hardening | E2E test suites, security penetration review, load testing, performance tuning | 🔲 Planned |
| **12** | Production Launch | Automated deployment pipeline, database backup routines, monitoring, smoke tests | 🔲 Planned |
