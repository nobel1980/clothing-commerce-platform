# Clothing Commerce Platform

A modern, high-performance, modular clothing e-commerce platform built as an enterprise-grade monorepo featuring a **Next.js 16** frontend (Storefront & Admin Backoffice), a domain-modular **Laravel 13** REST API on **PHP 8.3+**, **MySQL 8**, **Redis 7**, **NGINX**, and containerized orchestration. Designed for regional scalability and localized commerce with bKash, Nagad, Cash On Delivery (COD), Pathao, Steadfast, and REDX courier integrations.

---

## Architecture Overview

```
clothing-commerce-platform/
│
├── apps/
│   ├── web/                     # Next.js 16 App Router (Turbopack, React 19, Strict TS)
│   │   ├── src/app/(store)/     # Customer-facing storefront routes
│   │   ├── src/app/(admin)/     # Backoffice administrative portal routes
│   │   ├── src/app/api/health/  # Frontend healthcheck route
│   │   ├── src/features/        # Domain-driven features (catalog, cart, checkout, orders, admin)
│   │   ├── src/components/      # Reusable UI components & layouts
│   │   └── package.json
│   │
│   └── api/                     # Laravel 13 Modular Architecture (PHP 8.3+)
│       ├── app/Modules/         # 13 Isolated business domains
│       │   ├── Identity/        # Authentication, RBAC, Users, Audit Logs (✅ Implemented)
│       │   ├── Catalog/         # Categories, Products, Variants, Images, Attributes (✅ Implemented)
│       │   ├── Inventory/       # Stock levels, movements, reservations, warehouses
│       │   ├── Cart/            # Session & persistent cart operations
│       │   ├── Checkout/        # Two-stage checkout & price calculation
│       │   ├── Orders/          # Order lifecycle, status machine, snapshots
│       │   ├── Payments/        # Gateway adapters (bKash, Nagad, COD), refunds, webhooks
│       │   ├── Promotions/      # Coupons, discounts, anti-abuse rules
│       │   ├── Shipping/        # Courier adapters (Pathao, Steadfast, REDX), tracking
│       │   ├── Customers/       # Customer profiles, wishlists, address books
│       │   ├── Reviews/         # Ratings, reviews & moderation
│       │   ├── Notifications/   # Asynchronous transactional email & SMS
│       │   └── Reporting/       # Analytics, sales aggregation, read models
│       ├── app/Shared/          # Contracts, Events, Exceptions, Money value objects
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
│   ├── api/                     # API conventions & OpenAPI contracts
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
| **Identity** | ✅ Complete | Users, Roles, Permissions, Addresses, Audit Logs, Staff Authentication | `/api/auth/*`<br>`/api/admin/auth/*` |
| **Catalog** | ✅ Complete | Brands, Categories, Attributes, AttributeValues, Products, Variants, Images, Bulk Import, Search & Filtering | `/api/catalog/*`<br>`/api/admin/catalog/*` |
| **Inventory** | ✅ Complete | Warehouses, variant stock balances, reservations with expiry, movement ledger, low-stock alerts, reporting | `/api/inventory/*`<br>`/api/admin/inventory/*` |
| **Cart** | ✅ Complete | Guest & customer carts, merging, address & coupon selection | `/api/cart/*` |
| **Checkout** | ✅ Complete | Backend-authoritative quotes, stock revalidation under row locks, idempotent order placement | `/api/checkout/*` |
| **Orders** | ✅ Complete | Unique order numbers, item snapshots, status history, cancellation, customer history, admin lifecycle | `/api/orders/*`<br>`/api/admin/orders/*` |
| **Payments** | ✅ Complete | `PaymentGateway` interface; bKash, Nagad, SSLCommerz, COD adapters; server-side verification; idempotent webhooks; refunds; redacted audit logs | `/api/payments/*`<br>`/api/webhooks/*` |
| **Promotions** | 🟡 Partial | Coupon validation & discount calculation used by checkout (admin management planned) | `/api/checkout/validate-coupon` |
| **Shipping** | 🟡 Partial | Bangladesh shipping-rate calculation (courier integration planned) | `/api/checkout/shipping-rates` |
| **Customers** | 📋 Planned | Customer profiles, saved addresses, wishlists | `/api/customers/*` |
| **Reviews** | 📋 Planned | Verified buyer reviews, star ratings, moderation workflows | `/api/reviews/*` |
| **Notifications** | 📋 Planned | Asynchronous transactional emails, SMS alerts, in-app notifications | Internal Queue |
| **Reporting** | 📋 Planned | Sales summaries, revenue trends, inventory velocity read-models | `/api/admin/reports/*` |

---

## Implemented Modules Deep Dive

### 1. Identity Module (`app/Modules/Identity`)
- **Authentication**: Laravel Sanctum stateful cookies for SPA and tokens for API clients.
- **Role-Based Access Control**: Managed via `spatie/laravel-permission` with seeders and migration guarantees.
- **Address Management**: Multi-address management (billing/shipping) with default flag protection.
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
  - **Financial Precision**: All prices (`price`, `compare_at_price`, `cost_price`) are strictly validated and stored as positive **integers** in smallest currency subunits (cents/paisa). Floating-point arithmetic is prohibited.
  - **Zero Public Leaks**: Public storefront routes enforce strict `published()` query scopes. Drafts, archived items, future-dated products, and inactive categories return HTTP 404 to unauthenticated store visitors.
  - **Storage Abstraction**: Product image uploads utilize Laravel's `Storage` facade (`disk` + `path`). Images are never tied to hardcoded local file paths.
  - **Category Tree Integrity**: Circular parenting prevention (a category cannot be its own parent) and orphan protection on category deletion (children safely set `parent_id = null`).
  - **Bulk Import Foundation**: Extensible CSV/JSON product and variant importer (`ImportService`) executed within database transactions.

### 3. Orders & Payments Modules (`app/Modules/Orders`, `app/Modules/Payments`)
- **Separated statuses**: `status` (order), `payment_status` and `fulfilment_status` change independently; every change writes an immutable `order_status_histories` row.
- **Inventory via contract only**: cancel releases the stock reservation, ship commits it — always through `InventoryServiceContract`.
- **Gateways**: `BkashGatewayAdapter` (Tokenized Checkout v1.2.0-beta), `NagadGatewayAdapter` (v-0.2.0), `SslCommerzGatewayAdapter` (v4), `CodGatewayAdapter`, plus `MockPaymentGatewayAdapter` for tests, which refuses to run in production.
- **Payment safety**: server-side verification with exact amount (paisa), currency, merchant-reference and merchant-id matching; idempotency keys on initiation and refunds; webhook dedupe on `(gateway, gateway_event_id)`; HMAC webhook signatures; no DB locks held during gateway HTTP calls.
- **Delayed callbacks**: a payment verified after cancellation is recorded as `paid` and flagged `requires_manual_refund` without re-opening the order.
- **Redaction**: all gateway traffic is stored through `SensitiveDataRedactor`; credentials are env-only.
- Full contract: [docs/api/orders_and_payments_api.md](./docs/api/orders_and_payments_api.md).

---

## Catalog API Reference

### Public Storefront Routes (`/api/catalog/*`)
*No authentication required. Only active categories and published products are accessible.*

| Method | Endpoint | Description | Query Parameters |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/catalog/categories` | List active categories (root categories with active children) | — |
| `GET` | `/api/catalog/categories/{slug}` | Get category details with active descendant categories | — |
| `GET` | `/api/catalog/products` | Paginated product listing with filters and sorting | `search`, `category`, `brand`, `min_price`, `max_price`, `color`, `size`, `sort`, `per_page` |
| `GET` | `/api/catalog/products/{slug}` | Get detailed published product (with brand, category, images, active variants) | — |

### Admin Backoffice Routes (`/api/admin/catalog/*`)
*Requires `auth:sanctum` and staff privileges.*

| Method | Endpoint | Description |
| :--- | :--- | :--- |
| `GET` | `/api/admin/catalog/categories/tree` | Full hierarchical category tree |
| `GET` | `/api/admin/catalog/categories` | Paginated category list |
| `POST` | `/api/admin/catalog/categories` | Create new category (HTTP 201) |
| `GET` | `/api/admin/catalog/categories/{id}` | Get category detail |
| `PUT` | `/api/admin/catalog/categories/{id}` | Update category |
| `DELETE` | `/api/admin/catalog/categories/{id}` | Delete category (orphans children safely) |
| `GET` | `/api/admin/catalog/products` | List all products (drafts, published, archived) with admin filters |
| `POST` | `/api/admin/catalog/products` | Create product (HTTP 201) |
| `GET` | `/api/admin/catalog/products/{id}` | Get product detail |
| `PUT` | `/api/admin/catalog/products/{id}` | Update product metadata, brand, category, SEO |
| `DELETE` | `/api/admin/catalog/products/{id}` | Delete product and cascading images/variants |
| `POST` | `/api/admin/catalog/products/{id}/publish` | Transition status to `published` |
| `POST` | `/api/admin/catalog/products/{id}/unpublish`| Transition status to `draft` |
| `POST` | `/api/admin/catalog/products/{id}/variants` | Create product variant (HTTP 201) |
| `PUT` | `/api/admin/catalog/products/{id}/variants/{variantId}` | Update variant SKU, prices, color, size |
| `DELETE` | `/api/admin/catalog/products/{id}/variants/{variantId}` | Delete variant |
| `POST` | `/api/admin/catalog/products/{id}/images` | Upload product image using storage abstraction |
| `POST` | `/api/admin/catalog/products/{id}/images/reorder` | Batch update image sort orders |
| `DELETE` | `/api/admin/catalog/products/{id}/images/{imageId}` | Delete image file from storage and database |
| `POST` | `/api/admin/catalog/products/import` | Bulk import products via CSV or JSON payload |

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

# Run all feature tests
php artisan test

# Run Catalog module feature tests specifically
php artisan test --filter=Catalog

# Run Orders & Payments unit + feature tests
php artisan test --filter=OrdersAndPayments

# Verify queue job dispatch and execution
php artisan test:dispatch-job
php artisan queue:work --once

# Verify scheduler run
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
Heavy workflows (image processing, catalog sync, notifications, invoice generation) implement `Illuminate\Contracts\Queue\ShouldQueue`:

```bash
cd apps/api

# Run worker in daemon mode with resilience parameters
php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
```

In Docker environments, workers are managed automatically by the `queue` service via Supervisor (`infrastructure/supervisor/supervisord.conf`).

### Scheduler
Scheduled tasks are registered in `apps/api/routes/console.php`:

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
| [docs/api/api_conventions.md](./docs/api/api_conventions.md) | REST API design conventions, status codes, and error payloads |
| [docs/api/orders_and_payments_api.md](./docs/api/orders_and_payments_api.md) | Orders & Payments endpoints, status model, verification guarantees, gateway adapters |
| [docs/security/security-architecture.md](./docs/security/security-architecture.md) | Security framework, threat model, and defense-in-depth policies |
| [docs/deployment/deployment-guide.md](./docs/deployment/deployment-guide.md) | Production deployment, container setup, and rollback instructions |
| [docs/adr/](./docs/adr/) | Architectural Decision Records |

---

## Implementation Roadmap

| Phase | Milestone | Focus Area | Status |
| :---: | :--- | :--- | :---: |
| **01** | Foundation | Monorepo setup, Docker environments, Next.js 16, Laravel 13, CI | ✅ Complete |
| **02** | Identity & Auth | Sanctum auth, Spatie RBAC, user profiles, address book, audit logs | ✅ Complete |
| **03** | Catalog Management | Brands, categories, attributes, products, variants, images, search/filtering, bulk import | ✅ Complete |
| **04** | Inventory Management | Stock balances, warehouse locations, stock reservations, ledger movements | ✅ Complete |
| **05** | Storefront Portal | Customer catalog browsing, category tree, faceted filters, product details, SEO | ✅ Complete |
| **06** | Cart & Checkout | Session & persistent cart, two-stage checkout, pricing engine, lock acquisition | ✅ Complete |
| **07** | Orders & Payments | Order lifecycle state machine, bKash / Nagad / SSLCommerz / COD payment flows, webhooks | ✅ Complete |
| **08** | Admin Operations | Backoffice catalog management, inventory adjustments, order fulfillment | 🔲 Planned |
| **09** | Courier Integration | Pathao, Steadfast, REDX automated consignments, tracking, COD reconciliation | 🔲 Planned |
| **10** | Promotions & Reviews | Coupon validation engine, customer reviews, ratings moderation, wishlists | 🔲 Planned |
| **11** | Quality & Hardening | E2E test suites, security penetration review, load testing, performance tuning | 🔲 Planned |
| **12** | Production Launch | Automated deployment pipeline, database backup routines, monitoring, smoke tests | 🔲 Planned |
