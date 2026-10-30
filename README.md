# Clothing Commerce Platform

A modern, high-performance, modular clothing e-commerce platform built as a monorepo featuring a **Next.js 16** frontend and a modular **Laravel 13** backend.

---

## Repository Structure

```
├── apps/
│   ├── web/                            # Next.js 16 Frontends (Storefront & Admin)
│   │   ├── src/
│   │   │   ├── app/
│   │   │   │   ├── (store)/            # Customer-facing storefront
│   │   │   │   ├── (admin)/            # Backoffice admin panel
│   │   │   │   ├── login/
│   │   │   │   ├── register/
│   │   │   │   └── api/                # BFF / Webhooks
│   │   │   ├── features/               # Domain-driven features
│   │   │   ├── components/             # Reusable UI & Layout components
│   │   │   ├── lib/                    # API clients, auth & utilities
│   │   │   ├── hooks/
│   │   │   ├── stores/
│   │   │   └── types/
│   │   └── package.json
│   │
│   └── api/                            # Laravel 13 Modular REST API
│       ├── app/
│       │   ├── Modules/                # Domain-driven business modules
│       │   ├── Shared/                 # Cross-cutting contracts, events & money
│       │   └── Providers/
│       ├── bootstrap/
│       ├── config/
│       ├── database/
│       ├── routes/
│       ├── storage/
│       ├── tests/
│       └── composer.json
│
├── packages/                           # Shared packages across apps
│   ├── ui/                             # Shared UI components
│   ├── types/                          # Shared TypeScript contracts
│   ├── eslint-config/                  # Shared linting configuration
│   └── tsconfig/                       # Shared TypeScript configurations
│
├── infrastructure/                     # Deployment & local orchestration
│   ├── docker/
│   ├── nginx/
│   ├── mysql/
│   ├── redis/
│   └── monitoring/
│
├── docs/                               # Architecture, schema, workflows & ADRs
│   ├── architecture/
│   ├── database/
│   ├── api/
│   ├── workflows/
│   ├── security/
│   ├── deployment/
│   └── adr/
│
├── scripts/                            # CI/CD and maintenance scripts
├── .github/workflows/                  # GitHub Actions CI/CD workflows
├── docker-compose.yml
├── pnpm-workspace.yaml
├── package.json
├── AGENTS.md
├── .env.example
├── .gitignore
└── README.md
```

---

## Getting Started

### Prerequisites

- **Node.js**: >= 20.x
- **pnpm**: >= 9.x
- **PHP**: >= 8.3
- **Composer**: >= 2.x
- **Docker & Docker Compose** (Optional, recommended for local services)

### Quick Start

1. **Clone and install dependencies**:
   ```bash
   pnpm install
   ```

2. **Environment Configuration**:
   ```bash
   cp .env.example .env
   ```

3. **Start Development Environment**:
   ```bash
   # Run frontend
   pnpm dev
   ```
