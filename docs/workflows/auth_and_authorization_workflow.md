# Authentication & Authorization Workflow

## Overview

This document defines the complete authentication and authorization flows: customer registration/login, admin login, CSRF configuration, session management, RBAC enforcement, and audit logging.

---

## Authentication Strategy

| Client Type         | Method                          |
|---------------------|---------------------------------|
| Web storefront      | Sanctum cookie-based session    |
| Admin dashboard     | Sanctum cookie-based session    |
| Mobile / third-party | Sanctum personal access tokens |

> **Rule:** The browser frontend must never store session tokens or API keys in `localStorage` or `sessionStorage`. Use HttpOnly cookies exclusively.

---

## Customer Registration Flow

```
POST /api/v1/auth/register
        │
        ▼
Validate Form Request:
  - name (required)
  - email (required, unique)
  - phone (required, BD format)
  - password (min 8, confirmed)
        │
        ▼
Create User (role: customer)
        │
        ▼
Hash password with Bcrypt
        │
        ▼
Dispatch EmailVerification notification
        │
        ▼
Return UserResource (201)
  (no session created — user must verify email first, or per config)
```

---

## Customer Login Flow

```
POST /api/v1/auth/login
        │
        ▼
Rate limit check: 5 attempts / minute per IP
        │
        ▼
Validate credentials (email + password)
        │
        ▼
  ┌─────┴──────┐
 Fail         Pass
  │             │
  ▼             ▼
Return       Check email_verified_at
401            │
         ┌─────┴──────┐
       Unverified    Verified
         │             │
         ▼             ▼
       Return       Regenerate session
       403 +        (prevent session fixation)
       prompt         │
                      ▼
                 Set Sanctum session cookie
                 (HttpOnly, Secure, SameSite=Lax)
                      │
                      ▼
                 Return UserResource (200)
```

---

## Admin Login Flow

```
POST /api/v1/admin/auth/login
        │
        ▼
Rate limit: 3 attempts / minute per IP
        │
        ▼
Validate credentials
        │
        ▼
Check role: must have at least one admin role
(not a customer-only account)
        │
        ▼
  ┌─────┴──────┐
 No role     Has role
  │             │
  ▼             ▼
Return       Regenerate session
403            │
               ▼
         Set Sanctum session cookie
               │
               ▼
         Log audit: admin_login
               │
               ▼
         Return AdminUserResource (200)
```

---

## Logout Flow

```
POST /api/v1/auth/logout
        │
        ▼
Revoke current Sanctum token / session
        │
        ▼
Invalidate session
        │
        ▼
Clear session cookie
        │
        ▼
Return 204 No Content
```

---

## Password Reset Flow

```
POST /api/v1/auth/forgot-password
        │
        ▼
Rate limit: 3 attempts / 10 minutes per email
        │
        ▼
Look up user by email
        │
Always return 200
(Prevent email enumeration)
        │
If user found:
        ▼
Generate signed reset token
(stored in password_reset_tokens table)
        │
        ▼
Dispatch PasswordResetNotification (queued)

─────────────────────────────────────────────

POST /api/v1/auth/reset-password
        │
        ▼
Validate: token, email, password, confirmation
        │
        ▼
Verify token is valid and not expired (60 min)
        │
        ▼
Update password (bcrypt)
        │
        ▼
Delete reset token
        │
        ▼
Invalidate all existing sessions
        │
        ▼
Return 200
```

---

## Email Verification Flow

```
GET /api/v1/auth/email/verify/{id}/{hash}
        │
        ▼
Verify URL signature (Laravel signed routes)
        │
        ▼
Check user not already verified
        │
        ▼
Mark email_verified_at = now()
        │
        ▼
Dispatch CustomerRegistered event (queued notification)
        │
        ▼
Return 200

POST /api/v1/auth/email/resend
        │
        ▼
Rate limit: 1 per minute
        │
        ▼
Dispatch new verification email
```

---

## RBAC Model (Spatie Permission)

### Roles

| Role                | Description                                   |
|---------------------|-----------------------------------------------|
| `super_admin`       | Full platform access                          |
| `product_manager`   | Manage catalog, categories, images, variants  |
| `inventory_manager` | Manage stock, adjustments, warehouses         |
| `order_manager`     | Manage orders, fulfilment, cancellations      |
| `customer_support`  | View orders, customers, handle issues         |
| `marketing_manager` | Manage promotions, coupons, campaigns         |
| `report_viewer`     | Read-only access to reports and dashboards    |
| `customer`          | Public storefront customer (non-admin)        |

### Permission Seeding

Permissions are seeded at deployment:

```php
// Example permissions
'catalog.view', 'catalog.create', 'catalog.update', 'catalog.delete', 'catalog.publish'
'inventory.view', 'inventory.adjust'
'orders.view', 'orders.confirm', 'orders.cancel', 'orders.ship'
'customers.view', 'customers.manage'
'promotions.view', 'promotions.create', 'promotions.update', 'promotions.delete'
'reports.view'
'admin.users.view', 'admin.users.manage'
'admin.roles.manage'
```

### Role → Permission Mapping

| Role                | Permissions                                                    |
|---------------------|----------------------------------------------------------------|
| `super_admin`       | All permissions (wildcard via gate)                           |
| `product_manager`   | `catalog.*`                                                    |
| `inventory_manager` | `inventory.*`, `catalog.view`                                 |
| `order_manager`     | `orders.*`, `inventory.view`, `customers.view`                |
| `customer_support`  | `orders.view`, `customers.view`, `customers.manage`           |
| `marketing_manager` | `promotions.*`, `catalog.view`                                |
| `report_viewer`     | `reports.view`, `catalog.view`, `orders.view`                 |

---

## Route Authorization

```php
// Admin middleware stack
Route::middleware(['auth:sanctum', 'verified', 'role:super_admin|product_manager'])
    ->prefix('admin/products')
    ->group(function () {
        // ...
    });

// Permission-level check inside controller
$this->authorize('catalog.create');
```

---

## Privilege Escalation Prevention

- A user cannot assign a role higher than their own.
- `super_admin` assignment is seeder-only or via `super_admin` directly.
- API endpoints that modify roles require `admin.roles.manage` permission.
- Audit every role assignment and permission change.

---

## Session Security Configuration

```php
// config/session.php (production values)
'secure'    => true,      // HTTPS only
'http_only' => true,      // JS cannot read cookie
'same_site' => 'lax',     // CSRF protection
'expire_on_close' => false,
'lifetime'  => 120,       // Minutes
```

```php
// config/sanctum.php
'stateful' => [
    'www.meherfashion.com',
    'api.meherfashion.com',
    'localhost',
    '127.0.0.1',
],
```

---

## Rate Limiting

| Endpoint                         | Limit               |
|----------------------------------|---------------------|
| `POST /auth/login`               | 5 per minute / IP   |
| `POST /admin/auth/login`         | 3 per minute / IP   |
| `POST /auth/register`            | 10 per minute / IP  |
| `POST /auth/forgot-password`     | 3 per 10 min / email|
| `POST /auth/email/resend`        | 1 per minute / user |

---

## Audit Logging

Admin actions are logged in `audit_logs`:

```sql
audit_logs
├── id
├── user_id         -- Who performed the action
├── action          -- 'admin.login', 'role.assigned', 'order.cancelled', etc.
├── auditable_type  -- Model class name
├── auditable_id    -- Model ID
├── old_values      -- JSON snapshot before
├── new_values      -- JSON snapshot after
├── ip_address
├── user_agent
├── created_at
```

### Mandatory Audit Events

| Action                    | Audited Fields              |
|---------------------------|-----------------------------|
| Admin login               | IP, user_agent, timestamp   |
| Admin logout              | Timestamp                   |
| Role assigned/revoked     | User, role, actor           |
| Permission changed        | Before/after permissions    |
| Order confirmed           | Order ID, actor             |
| Order cancelled           | Order ID, reason, actor     |
| Product published         | Product ID, actor           |
| Stock adjusted            | Variant, qty change, reason |
| Refund initiated          | Order ID, amount, actor     |

---

## CSRF Configuration

- All state-changing requests from the browser **must** include a CSRF token.
- CSRF cookie is set on `GET /sanctum/csrf-cookie` — the frontend must call this before the first mutation.
- Next.js middleware should automatically include CSRF headers on mutating requests.
- API-only (token-auth) clients are exempt from CSRF checks.
