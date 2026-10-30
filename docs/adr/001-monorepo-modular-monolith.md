# ADR 001: Monorepo Structure & Modular Monolith Architecture

## Context and Problem Statement

We are building a single-brand clothing commerce platform targeting the Bangladesh market, anticipating an initial catalog of 1,000–20,000 SKUs. We need an architecture that minimizes operational overhead, ensures rapid feature delivery, preserves data consistency, and allows future domain service extraction as the business scales.

## Decision Drivers

* Avoid unnecessary distributed system complexity (network latency, distributed transactions, service discovery).
* Maintain clear domain boundaries so individual domains (Catalog, Inventory, Orders) can be extracted into microservices if needed.
* Share TypeScript API types seamlessly between frontend and backend contracts.

## Considered Options

1. **Microservices Architecture** (Multiple repositories, independent databases).
2. **Traditional Monolith** (Single codebase, unstructured tight coupling across tables).
3. **Monorepo with Modular Monolith** (pnpm workspace, single Next.js web app, single Laravel API with domain module boundaries).

## Decision Outcome

**Chosen Option:** **Option 3 (Monorepo with Modular Monolith)**.

### Positive Consequences
* Single deployment target for backend and unified frontend app.
* Logical module separation enforces database ownership without requiring separate physical database servers initially.
* Shared TypeScript contracts under `packages/types/` prevent frontend/backend API payload drift.

### Negative Consequences
* Developers must remain disciplined about not making direct cross-module database queries in Laravel Eloquent.