# Clothing Commerce Platform

A modern, high-performance, modular clothing e-commerce platform built as an enterprise-grade monorepo featuring a **Next.js 16** frontend (Storefront & Admin Backoffice), a modular **Laravel 13** backend on **PHP 8.3+**, **MySQL 8**, **Redis 7**, **NGINX**, and containerized orchestration. Designed for the Bangladesh market with bKash, Nagad, COD, Pathao, Steadfast, and REDX integrations.

---

## Architecture Overview

```
clothing-commerce-platform/
│
├── apps/
│   ├── web/                     # Next.js 16 App Router (Turbopack, React 19, Strict TS)
│   │   ├── src/app/(store)/     # Customer-facing storefront
│   │   ├── src/app/(admin)/     # Backoffice administrative portal
│   │   ├── src/app/api/health/  # Frontend healthcheck route
│   │   ├── src/features/        # Domain-driven features (catalog, cart, checkout, orders, admin)
│   │   ├── src/components/      # Reusable UI components & layouts
│   │   └── package.json
│   │
│   └── api/                     # Laravel 13 Modular Architecture (PHP 8.3+)
│       ├── app/Modules/         # Isolated business domains (13 modules)
│       ├── app/Shared/          # Contracts, Events, Exceptions, Money value objects
│       ├── app/Jobs/            # Asynchronous Queue Jobs
│       ├── routes/              # api.php, console.php (scheduler), web.php
│       └── composer.json
│
├── packages/                    # Shared TypeScript packages
│   ├── ui/                      # Shared design system components (Button, Badge, Card, etc.)
│   ├── types/                   # Cross-cutting TypeScript contracts & API models
│   ├── eslint-config/           # Shared linting configs (base & next)
│   └── tsconfig/                # Shared compiler configs (base, nextjs, react-library)
│
├── infrastructure/              # Containerization, Reverse Proxy & Storage
│   ├── docker/                  # Dockerfiles (Dockerfile.web, Dockerfile.api, Dockerfile.nginx)
│   ├── supervisor/              # Supervisord & queue worker daemon configs
│   ├── nginx/                   # Reverse proxy gateway & rate-limiting configs
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
| **Language** | TypeScript (Strict) | `5.6+` | Type safety across web, types, and ui packages |
| **Backend** | Laravel Framework | `13.x` | Domain-modular REST API, queue processing & scheduling |
| **Runtime** | PHP CLI / FPM | `8.3+` | High performance PHP engine with opcache & JIT |
| **Database** | MySQL | `8.0 / 8.3` | Relational storage with persistent volumes & UTF8mb4 |
| **Cache/Queue** | Redis | `7.x` | Queue broker, session driver, and distributed caching |
| **Gateway** | NGINX | `1.27 Alpine` | Reverse proxy, SSL termination, and rate limiting |
| **Auth** | Laravel Sanctum | `4.x` | Cookie-based stateful authentication |
| **RBAC** | Spatie Permission | `6.x` | Role-based access control for admin |
| **Package Mgr** | pnpm | `9.x` | Fast, deterministic monorepo dependency management |

---

## Domain Modules (Laravel)

| Module | Owns | API Prefix |
| :--- | :--- | :--- |
| **Identity** | Users, roles, permissions, sessions, audit logs | `/auth`, `/admin/auth` |
| **Catalog** | Products, categories, brands, variants, images | `/catalog`, `/admin/products` |
| **Inventory** | Stock, warehouses, movements, reservations | `/admin/inventory` |
| **Cart** | Cart items, quantity management | `/cart` |
| **Checkout** | Pricing orchestration, order placement | `/checkout` |
| **Orders** | Order lifecycle, status history, snapshots | `/orders`, `/admin/orders` |
| **Payments** | Transactions, gateway adapters, refunds | `/payments`, `/webhooks` |
| **Promotions** | Coupons, discount rules, usage limits | `/admin/promotions` |
| **Shipping** | Courier adapters, consignments, tracking | `/shipping`, `/admin/shipments` |
| **Customers** | Addresses, profiles, wishlists | `/customers` |
| **Reviews** | Product reviews, moderation | `/reviews` |
| **Notifications** | Email, SMS, in-app notifications | — |
| **Reporting** | Sales summaries, read models | `/admin/reports` |

---

## Admin Roles (RBAC)

| Role | Permissions |
| :--- | :--- |
| `super_admin` | Full platform access |
| `product_manager` | Catalog, categories, products, images, publishing |
| `inventory_manager` | Stock, warehouses, adjustments |
| `order_manager` | Orders, fulfilment, cancellations, refunds |
| `customer_support` | View orders, view customers |
| `marketing_manager` | Coupons, promotions, campaigns |
| `report_viewer` | Read-only reports & dashboards |

---

## Prerequisites

- **Node.js**: `>= 20.x` (Recommended: v22 LTS or v24)
- **pnpm**: `>= 9.x` (`corepack enable && corepack prepare pnpm@9.0.0 --activate`)
- **PHP**: `>= 8.3` with extensions: `pdo_mysql`, `bcmath`, `opcache`, `intl`, `mbstring`, `zip`, `xml`
- **Composer**: `>= 2.7`
- **Docker & Docker Compose**: (Optional for host mode, recommended for containerized stack)

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

3. **Run Migrations & Seed (first time)**:
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

2. **Install Laravel API Dependencies**:
   ```bash
   cd apps/api
   composer install
   cp .env.example .env
   php artisan key:generate
   php artisan migrate --seed
   ```

3. **Run Next.js 16 Web App**:
   ```bash
   pnpm dev
   ```

4. **Run Laravel 13 API Server**:
   ```bash
   cd apps/api
   php artisan serve --port=8000
   ```

---

## Health Check Endpoints

| Service | Endpoint | Description | Sample Output |
| :--- | :--- | :--- | :--- |
| **Next.js 16** | `GET /api/health` | Returns frontend health, uptime, and status | `{"status":"healthy","service":"clothing-commerce-web","uptimeSeconds":142}` |
| **Laravel 13** | `GET /api/health` | Tests MySQL connection, Redis, and queue drivers | `{"status":"healthy","checks":{"database":{"status":"ok"},"queue":{"status":"ok"}}}` |
| **NGINX** | `GET /nginx-health` | Fast status probe for load balancers | `healthy` |

---

## Background Workers & Scheduling

### Queue Worker

```bash
# Process a test job
cd apps/api
php artisan test:dispatch-job
php artisan queue:work --once

# Run in daemon mode
php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
```

In Docker, workers are managed automatically by the `queue` container using Supervisor.

### Scheduler

Configured in `apps/api/routes/console.php`.

```bash
# List scheduled tasks
cd apps/api
php artisan schedule:list

# Run pending tasks manually
php artisan schedule:run
```

In Docker, the `scheduler` container runs `php artisan schedule:work` continuously.

---

## Production Deployment

Production uses `docker-compose.prod.yml` with:
- Non-root users (`nextjs:nodejs` and `www-data`)
- Multi-stage builds with standalone output
- Persistent volumes (`mysql_data_prod`, `redis_data_prod`, `api_storage_prod`)
- Multi-replica scaling for `web` and `api`
- Hard resource CPU and memory limits

```bash
cp .env.production.example .env.production
docker compose -f docker-compose.prod.yml up -d --build
```

See [docs/deployment/deployment-guide.md](./docs/deployment/deployment-guide.md) for the complete runbook.

---

## Continuous Integration (CI)

A complete GitHub Actions CI pipeline is located at `.github/workflows/ci.yml`:

1. **Frontend**: Monorepo dependency freeze check, strict TypeScript check across `@clothing-commerce/types`, `@clothing-commerce/ui`, and `apps/web`, and Next.js 16 production build.
2. **Backend**: PHP 8.3 test matrix with active MySQL 8 & Redis 7 services, database migration run, test job dispatch, and queue worker execution.
3. **Docker**: Configuration validation and linting for both development and production compose stacks.

---

## Documentation

| Document | Description |
| :--- | :--- |
| [AGENTS.md](./AGENTS.md) | Coding & architectural guidelines for contributors |
| [docs/workflows/Planning.md](./docs/workflows/Planning.md) | Master software architecture blueprint (2,300+ lines) |
| [docs/workflows/README.md](./docs/workflows/README.md) | Workflow index & cross-domain dependency map |
| [docs/workflows/checkout_workflow.md](./docs/workflows/checkout_workflow.md) | Two-stage checkout, inventory locking, idempotency |
| [docs/workflows/auth_and_authorization_workflow.md](./docs/workflows/auth_and_authorization_workflow.md) | Auth, RBAC, CSRF, session security |
| [docs/workflows/order_lifecycle_workflow.md](./docs/workflows/order_lifecycle_workflow.md) | Order state machine, snapshots, cancellation |
| [docs/workflows/payment_workflow.md](./docs/workflows/payment_workflow.md) | Gateway adapters, webhooks, refunds |
| [docs/workflows/inventory_management_workflow.md](./docs/workflows/inventory_management_workflow.md) | Stock, reservations, movements, low-stock |
| [docs/workflows/courier_shipping_workflow.md](./docs/workflows/courier_shipping_workflow.md) | Pathao/Steadfast/REDX, tracking, COD reconciliation |
| [docs/workflows/product_publishing_workflow.md](./docs/workflows/product_publishing_workflow.md) | Product creation, validation, publish, cache invalidation |
| [docs/workflows/promotions_workflow.md](./docs/workflows/promotions_workflow.md) | Coupons, discount engine, anti-abuse |
| [docs/architecture/system_architecture.md](./docs/architecture/system_architecture.md) | System-level architecture overview |
| [docs/architecture/backend_architecture.md](./docs/architecture/backend_architecture.md) | Laravel modular monolith design |
| [docs/architecture/frontend_architecture.md](./docs/architecture/frontend_architecture.md) | Next.js App Router design |
| [docs/database/database-design.md](./docs/database/database-design.md) | ERD, table ownership, indexing strategy |
| [docs/api/api_conventions.md](./docs/api/api_conventions.md) | API versioning, response format, HTTP codes |
| [docs/security/security-architecture.md](./docs/security/security-architecture.md) | Threat model, security controls, checklist |
| [docs/deployment/deployment-guide.md](./docs/deployment/deployment-guide.md) | Local setup, production deploy, CI/CD, rollback |
| [docs/adr/](./docs/adr/) | Architecture Decision Records |

---

## Implementation Roadmap

| Phase | Focus | Estimated Time |
| :--- | :--- | :--- |
| ✅ **01** | Repository Foundation (monorepo, Docker, Next.js, Laravel, CI) | Done |
| ✅ **02** | Identity & Authorization (Sanctum, RBAC, audit logging) | Done |
| 🔲 **03** | Catalog Management (categories, products, variants, images) | 5–7 days |
| 🔲 **04** | Inventory Management (stock, reservations, movements) | 3–5 days |
| 🔲 **05** | Storefront (homepage, catalog, search, SEO) | 5–7 days |
| 🔲 **06** | Cart & Checkout (two-stage checkout, pricing engine) | 4–6 days |
| 🔲 **07** | Orders & Payments (COD, bKash, Nagad, gateway integration) | 5–7 days |
| 🔲 **08** | Admin Operations (dashboard, order management, reports) | 4–6 days |
| 🔲 **09** | Courier Integration (Pathao, Steadfast, REDX) | 3–5 days |
| 🔲 **10** | Promotions & Reviews (coupons, campaigns, wishlist) | 3–4 days |
| 🔲 **11** | Quality & Security (E2E, load tests, penetration review) | 5–7 days |
| 🔲 **12** | Production Launch (CI/CD, backups, monitoring, smoke tests) | 3–5 days |

**Estimated total:** 45–66 working days
