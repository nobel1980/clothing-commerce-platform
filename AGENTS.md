# Agent Guidelines & Repository Architecture

Welcome to the **Clothing Commerce Platform** monorepo. This file documents core architectural principles, domain module boundaries, and conventions for AI and human contributors.

## Monorepo Architecture Overview

- **apps/web**: Next.js 16 App Router application. Contains storefront (`(store)`), backoffice portal (`(admin)`), feature-driven state, and reusable modules.
- **apps/api**: Laravel 13 Modular Architecture. Domain logic is segregated into self-contained business modules (`Identity`, `Catalog`, `Inventory`, `Orders`, `Payments`, etc.) and shared cross-cutting contracts.
- **packages/**: Shared TypeScript libraries across apps (`ui`, `types`, `eslint-config`, `tsconfig`).
- **infrastructure/**: Containerization, reverse proxies, and database definitions.
- **docs/**: Architectural Decision Records (ADRs), workflow diagrams, and database schemas.

## Modular Backend Principles (`apps/api`)

1. **High Cohesion, Low Coupling**: Each module under `app/Modules/*` manages its own models, repositories, actions/services, and endpoints.
2. **Contracts & Shared Logic**: Cross-module communication should happen via explicit interfaces in `app/Shared/Contracts` or event-driven integration through `app/Shared/Events`.
3. **Money & Precision**: Use `app/Shared/Money` objects for all price and currency calculations.

## Storefront & Admin Frontend Principles (`apps/web`)

1. **Route Groups**:
   - `(store)` for public ecommerce routes.
   - `(admin)` for authenticated staff backoffice operations.
2. **Feature Architecture**: Reusable business domain hooks and logic live in `src/features/<module>`.
3. **Strict Typing**: Consume shared API contract types from `@clothing-commerce/types` or `src/types`.
