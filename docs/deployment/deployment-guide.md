# Deployment Guide

## Overview

This document describes the deployment process for the Clothing Commerce Platform: local development setup, production Docker deployment, CI/CD pipeline, and operational runbooks.

---

## Environments

| Environment | Purpose | Stack |
|-------------|---------|-------|
| `development` | Local developer machines | Docker Compose or host-native |
| `staging` | Pre-production QA environment | Docker Compose (prod-like) |
| `production` | Live platform | Docker Compose (multi-replica) |

---

## Environment Files

| File | Purpose |
|------|---------|
| `.env.development.example` | Template for local development |
| `.env.production.example` | Template for production |
| `.env.example` | Baseline minimal template |

> **Rule:** Never commit `.env` files. Copy from the appropriate `.example` file and fill in real values.

---

## Local Development Setup

### Option A: Docker Compose (Recommended)

```bash
# 1. Clone the repository
git clone <repo-url>
cd clothing-commerce-platform

# 2. Configure environment
cp .env.development.example .env

# 3. Start all services
docker compose up --build

# 4. Run Laravel migrations (first time only)
docker compose exec api php artisan migrate --seed

# 5. Verify health
curl http://localhost:3000/api/health  # Next.js
curl http://localhost/api/health        # Laravel via NGINX
```

### Option B: Host-Native Development

```bash
# 1. Install monorepo JS dependencies
pnpm install

# 2. Set up Laravel API
cd apps/api
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed

# 3. Start Next.js (terminal 1)
cd ../..
pnpm dev

# 4. Start Laravel API (terminal 2)
cd apps/api
php artisan serve --port=8000

# 5. Start queue worker (terminal 3)
cd apps/api
php artisan queue:work redis --sleep=3 --tries=3
```

---

## Docker Services

| Service | Container Name | Port | Purpose |
|---------|---------------|------|---------|
| Next.js | `commerce-web` | 3000 | Storefront + Admin |
| Laravel | `commerce-api` | 9000 (FPM) | REST API |
| NGINX | `commerce-nginx` | 80, 443 | Reverse proxy |
| MySQL | `commerce-mysql` | 3306 | Primary database |
| Redis | `commerce-redis` | 6379 | Cache + Queue |
| Queue worker | `commerce-worker` | — | Async job processing |
| Scheduler | `commerce-scheduler` | — | Cron job execution |

---

## Health Check Endpoints

| Service | Endpoint | Expected Response |
|---------|----------|-------------------|
| Next.js | `GET /api/health` | `{"status":"healthy"}` |
| Laravel | `GET /api/health` | `{"status":"healthy","checks":{...}}` |
| NGINX | `GET /nginx-health` | `healthy` |

---

## Production Deployment

### Prerequisites

- Docker Engine 24+
- Docker Compose v2.24+
- Domain names pointing to server IP (A records configured)
- SSL certificate (Let's Encrypt recommended)

### Production Deployment Steps

```bash
# 1. Set up production environment
cp .env.production.example .env.production
# Edit .env.production with real production values

# 2. Build and start production stack
docker compose -f docker-compose.prod.yml up -d --build

# 3. Run database migrations
docker compose -f docker-compose.prod.yml exec api php artisan migrate --force

# 4. Seed initial roles and permissions (first deploy only)
docker compose -f docker-compose.prod.yml exec api php artisan db:seed --class=RolesAndPermissionsSeeder

# 5. Create first super_admin user
docker compose -f docker-compose.prod.yml exec api php artisan tinker
# App\Modules\Identity\Models\User::create([...]) and assign role

# 6. Verify all containers are healthy
docker compose -f docker-compose.prod.yml ps
docker compose -f docker-compose.prod.yml logs --tail=50 api
```

### Production Security Checklist

- [ ] `.env.production` contains no example/placeholder values
- [ ] `APP_KEY` is a unique 32-character random key
- [ ] `APP_DEBUG=false`
- [ ] `APP_ENV=production`
- [ ] All database passwords are strong and unique
- [ ] Redis has `requirepass` set
- [ ] NGINX HTTPS configured with valid certificate
- [ ] Docker containers run as non-root users

---

## CI/CD Pipeline (GitHub Actions)

The pipeline is defined at `.github/workflows/ci.yml`.

### Pipeline Stages

```
On: push / pull_request to main or develop

┌─────────────────┐
│ 1. Frontend CI  │
│  - pnpm install │
│  - typecheck    │
│  - next build   │
└────────┬────────┘
         │
┌────────▼────────┐
│ 2. Backend CI   │
│  - composer     │
│  - php tests    │
│  - queue test   │
└────────┬────────┘
         │
┌────────▼────────┐
│ 3. Docker CI    │
│  - compose lint │
│  - config valid │
└─────────────────┘
```

### Environment Secrets (GitHub Secrets)

Add these to your GitHub repository secrets:

| Secret | Description |
|--------|-------------|
| `PROD_APP_KEY` | Laravel APP_KEY for production |
| `PROD_DB_PASSWORD` | MySQL production password |
| `PROD_REDIS_PASSWORD` | Redis production password |
| `DEPLOY_SSH_KEY` | SSH key for deployment server |
| `DEPLOY_HOST` | Production server IP/hostname |

---

## Database Migrations

### Development

```bash
# Run all pending migrations
php artisan migrate

# Rollback last batch
php artisan migrate:rollback

# Refresh with seeding (destroys all data)
php artisan migrate:fresh --seed
```

### Production

```bash
# Always use --force in production (skips "are you sure?" prompt)
php artisan migrate --force
```

> **Rule:** Never run `migrate:fresh` or `migrate:reset` in production. Only `migrate --force`.

---

## Queue Workers

### Running in Production (Supervisor via Docker)

Queue workers are managed by Supervisor inside the `commerce-worker` container.

```bash
# Check queue status
docker compose exec worker supervisorctl status

# Restart workers (after code deploy)
docker compose exec worker supervisorctl restart laravel-worker:*

# Monitor failed jobs
docker compose exec api php artisan queue:failed
```

### Queue Retry Policy

- Max attempts: `--tries=3`
- Max execution time: `--max-time=3600` (1 hour)
- Backoff: Exponential (retry after 60s, 300s, 900s)
- Failed jobs stored in `failed_jobs` table for inspection.

---

## Scheduler

The `commerce-scheduler` container runs `php artisan schedule:work` continuously.

### Scheduled Jobs

| Job | Schedule | Purpose |
|-----|----------|---------|
| `track:shipments` | Every 30 minutes | Sync courier tracking |
| `inventory:expire-reservations` | Every minute | Release expired stock reservations |
| `reconcile:cod` | Daily at 02:00 | COD payment reconciliation |
| `reports:daily-summary` | Daily at 06:00 | Generate daily sales report |
| `queue:prune-failed` | Weekly | Clean up old failed jobs |

---

## Rollback Procedure

```bash
# 1. Deploy previous image tag
docker compose -f docker-compose.prod.yml pull <previous-tag>
docker compose -f docker-compose.prod.yml up -d

# 2. Rollback database migrations if needed (destructive!)
php artisan migrate:rollback --step=1

# 3. Verify health endpoints
curl https://api.yourdomain.com/api/health
```

> **Rule:** Always test rollback procedures in staging before relying on them in production.

---

## Backup Strategy

- **MySQL:** Daily automated dump via `mysqldump` + compressed upload to object storage.
- **Redis:** RDB snapshots enabled (`save 900 1` in redis.conf).
- **Uploaded media:** Object storage with versioning enabled.
- **Retention:** 30 days of daily backups, 12 months of monthly backups.
- **Testing:** Restore test performed monthly.
