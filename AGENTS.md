# Agent Guidelines & Repository Architecture

Welcome to the **Clothing Commerce Platform** monorepo. This document establishes core architectural patterns, domain boundaries, security rules, and code quality standards for AI agents and human contributors.

---

## 1. Monorepo Overview & Package Boundaries

The repository is managed via **pnpm workspaces** and composed of application containers and shared packages:

- **`apps/web`**: Next.js 16 App Router application.
  - Public Storefront: Located under `src/app/(store)/`.
  - Admin Backoffice: Located under `src/app/(admin)/admin/`.
  - Feature State & Logic: Located under `src/features/<domain>/` (e.g. `catalog`, `cart`, `checkout`, `orders`, `customers`, `admin`).
  - Healthcheck Probe: `src/app/api/health/route.ts`.
- **`apps/api`**: Laravel 13 Modular REST API (PHP 8.3+).
  - Business Modules: Located under `app/Modules/<Domain>/` (`Identity`, `Catalog`, `Inventory`, `Cart`, `Checkout`, `Orders`, `Payments`, `Promotions`, `Shipping`, `Customers`, `Reviews`, `Notifications`, `Reporting`).
  - Shared Domain Logic: Located under `app/Shared/` (`Contracts`, `Events`, `Exceptions`, `Money`, `Support`).
  - Healthcheck Probe: Route `GET /api/health` defined in `routes/api.php`.
- **`packages/types` (`@clothing-commerce/types`)**:
  - Central source of truth for cross-tier data models, API payloads, and enum contracts.
- **`packages/ui` (`@clothing-commerce/ui`)**:
  - Headless & styled reusable UI primitives (`Button`, `Badge`, `Card`, `StatusIndicator`).
- **`packages/tsconfig` & `packages/eslint-config`**:
  - Shared TypeScript compiler options and ESLint rules enforcing strict mode.
- **`infrastructure/`**:
  - Dockerfiles (`Dockerfile.web`, `Dockerfile.api`, `Dockerfile.nginx`), Supervisor configs, NGINX routing, MySQL 8 my.cnf/init, and Redis configs.

---

## 2. Frontend Development Principles (`apps/web`)

1. **Route Groups & URL Separation**:
   - `(store)` route group handles public browsing without modifying the URL path.
   - `(admin)` route group isolates authenticated staff administrative views under `/admin/*`.
2. **Strict TypeScript**:
   - Always run with `strict: true`, `noImplicitAny: true`, and `strictNullChecks: true`.
   - Never use `any`. Always import or define types from `@clothing-commerce/types`.
3. **Data Fetching & Components**:
   - Favor React Server Components (RSC) for data fetching and SEO-critical catalog pages.
   - Use `"use client"` only when interactivity, browser APIs, or React hooks are strictly required.
4. **State Management**:
   - Reusable domain logic lives inside `src/features/<domain>/hooks`.
   - UI components must not directly manipulate cross-cutting global state without custom feature hooks.

---

## 3. Backend Development Principles (`apps/api`)

1. **Modular Domain Isolation**:
   - Each module under `app/Modules/<Domain>` should encapsulate its own Models, Controllers, Repositories, and Actions.
   - Cross-module communication must occur via explicit contracts (`app/Shared/Contracts`) or event dispatching (`app/Shared/Events`).
2. **Financial Precision & Money**:
   - **NEVER** use floating-point numbers (`float` or `double`) for monetary amounts or calculations.
   - Always store currency amounts in the smallest subunit (e.g. integer cents in database) and use `Money` value objects.
3. **Database Migrations & MySQL Compatibility**:
   - Always ensure `Schema::defaultStringLength(191)` is respected to prevent MySQL `utf8mb4` key length errors (1071).
   - Foreign keys must always have indexes and cascade rules specified.
4. **Queue Workers & Asynchronous Execution**:
   - Heavy tasks (email sending, order invoices, webhook delivery, inventory sync) must implement `Illuminate\Contracts\Queue\ShouldQueue`.
   - Always specify retry counts (`--tries=3`) and max execution limits (`--max-time=3600`).
5. **Scheduler**:
   - Register recurring jobs in `routes/console.php` with descriptive names.

---

## 4. Security & Environment Rules

1. **Environment Variables**:
   - **NEVER COMMIT `.env` FILES TO GIT.**
   - All `.env` variations (`.env`, `.env.local`, `.env.production`) must remain listed in `.gitignore`.
   - When introducing new configuration keys, add them to `.env.example`, `.env.development.example`, and `.env.production.example` with sensible dummy defaults or placeholders.
2. **Docker Hardening**:
   - Do not run processes as `root` in production images.
   - Next.js uses non-root user `nextjs:nodejs` (`uid: 1001`).
   - Laravel API uses non-root user `www-data`.
   - NGINX uses non-root user `nginx`.
   - Secrets must be injected via runtime environment variables or Docker secrets, never baked into Docker images.
3. **Healthchecks**:
   - All containerized services must expose a healthcheck probe to ensure orchestration reliability.

---

## 5. Validation Checklist

Before submitting code changes, verify:
- [ ] `pnpm --filter @clothing-commerce/types typecheck` passes with zero errors.
- [ ] `pnpm --filter @clothing-commerce/ui typecheck` passes with zero errors.
- [ ] `pnpm --filter web typecheck` and `pnpm --filter web build` pass.
- [ ] `apps/api`: `php artisan test:dispatch-job` and `php artisan queue:work --once` succeed.
- [ ] `apps/api`: `php artisan schedule:run` executes without errors.
- [ ] Health endpoints return HTTP 200:
  - `GET /api/health` on `apps/web`
  - `GET /api/health` on `apps/api`
  - `GET /nginx-health` on `nginx`
