# System Architecture Record

## 1. High-Level Architecture Overview

The Clothing Commerce Platform is designed as a single-brand, single-store commerce engine tailored for the Bangladesh e-commerce market. The architecture follows a **Modular Monolith** pattern within a **Monorepo** configuration.

```
+-----------------------------------------------------------------------+
|                             CLIENT LAYER                              |
|                                                                       |
|   +---------------------------------------------------------------+   |
|   |                  Next.js 16 Web Application                   |   |
|   |                                                               |   |
|   |   (store) Customer Storefront     (admin) Admin Dashboard     |   |
|   +---------------------------------------------------------------+   |
+-----------------------------------------------------------------------+
                                    |
                             REST API / Cookie Auth
                                    |
+-----------------------------------------------------------------------+
|                            BACKEND LAYER                              |
|                                                                       |
|   +---------------------------------------------------------------+   |
|   |                  Laravel 13 Modular Monolith                  |   |
|   |                                                               |   |
|   |  Identity  | Catalog   | Inventory | Cart      | Checkout     |   |
|   |  Orders    | Payments  | Shipping  | Promotion | Reviews      |   |
|   +---------------------------------------------------------------+   |
+-----------------------------------------------------------------------+
      |                   |                     |                   |
      v                   v                     v                   v
+-----------+       +-----------+       +----------------+   +--------------+
|  MySQL 8  |       |   Redis   |       | Bangladesh     |   | Courier      |
| (Database)|       | (Cache &  |       | Gateways       |   | Adapters     |
|           |       |   Queue)  |       | (bKash/Nagad)  |   | (Pathao/etc) |
+-----------+       +-----------+       +----------------+   +--------------+
```

---

## 2. Key Architectural Decisions

| Decision Area | Selected Strategy | Rationale |
| :--- | :--- | :--- |
| **Monorepo Workspace** | `pnpm` workspace (`apps/*`, `packages/*`) | Simplifies shared TypeScript contracts, UI components, and build pipelines. |
| **Monolith vs Microservices** | Modular Monolith | Eliminates distributed system complexity for single-brand scope while maintaining strict domain isolation for future microservice extraction. |
| **Frontend Strategy** | Next.js 16 App Router (Unified App) | Combines storefront and admin into one Next.js project using Route Groups `(store)` and `(admin)` to reduce deployment overhead. |
| **Database Ownership** | Single MySQL 8 instance with module ownership | Simplifies operational management while enforcing logical module-level schema boundaries. |
| **Concurrency & Reservation** | Database transactions with pessimistic locks | Ensures zero stock overselling during flash sales or heavy checkout traffic. |
| **Event Architecture** | Transactional Outbox Pattern | Guarantees reliable domain event delivery to queues or future message brokers (e.g., Kafka). |

---

## 3. Technology Stack Reference

* **Frontend:** Next.js 16, TypeScript (Strict), Tailwind CSS, `shadcn/ui`, TanStack Query, Zustand, React Hook Form, Zod.
* **Backend:** Laravel 13, PHP 8.3+, Laravel Sanctum, Spatie Permission, OpenAPI/Swagger specs.
* **Persistence & Caching:** MySQL 8 (InnoDB), Redis 7+ (Caching, Session, Distributed Locks, Queues).
* **Infrastructure:** Docker Compose, NGINX Reverse Proxy, GitHub Actions CI/CD.

---

## 4. Local Development Environment

Services managed via Docker Compose:
* `commerce-web`: Next.js 16 app (`:3000`)
* `commerce-api`: Laravel 13 PHP-FPM app (`:8000`)
* `commerce-nginx`: Reverse Proxy (`:8080`)
* `commerce-mysql`: Primary Database (`:3306`)
* `commerce-redis`: Cache, Session & Queue Store (`:6379`)
* `commerce-worker`: Laravel Queue Worker
* `commerce-scheduler`: Laravel Cron Scheduler