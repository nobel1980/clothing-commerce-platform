# Architecture Decision Records (ADR Index)

This directory tracks historical architectural decision logs for the Clothing Commerce Platform.

## ADR Index

| ADR # | Title | Date | Status | Summary |
| :--- | :--- | :--- | :--- | :--- |
| [ADR-001](001-monorepo-modular-monolith.md) | Monorepo Structure & Modular Monolith | 2026-10-30 | **Accepted** | Selected a single monorepo with Next.js 16 and Laravel 13 modular monolith over microservices. |
| [ADR-002](002-inventory-reservation-strategy.md) | Pessimistic Locking & Stock Reservations | 2026-10-30 | **Accepted** | Selected pessimistic database locks (`FOR UPDATE`) with a 15-minute reservation expiry mechanism. |