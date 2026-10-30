# Security Architecture

## Overview

This document defines the security posture, threat model, and security controls for the Clothing Commerce Platform. Security is designed into every layer rather than bolted on after the fact.

---

## Threat Model — Top Risks

| Threat | Vector | Mitigation |
|--------|--------|------------|
| Broken authentication | Weak sessions, token leakage | Sanctum HttpOnly cookies, no localStorage |
| Privilege escalation | Role bypass on admin APIs | Spatie Permission + policy gates |
| SQL injection | Raw DB queries | Eloquent ORM + parameterized queries only |
| Payment fraud | Fake webhooks, amount tampering | Server-side verify, HMAC signature check |
| Inventory overselling | Race conditions at checkout | Pessimistic locking + DB transactions |
| Data enumeration | Predictable IDs, verbose errors | UUID references, generic error messages |
| CSRF | Cross-origin state mutations | Sanctum CSRF cookie, SameSite=Lax |
| XSS | User-supplied content rendering | Next.js escaping + CSP headers |
| Credential exposure | .env in git, secrets in images | .gitignore, Docker secrets injection |
| DDoS / brute force | Login flooding | Rate limiting per IP and per email |

---

## Layer-by-Layer Controls

### 1. Network Layer (NGINX)

```nginx
# Rate limiting zones
limit_req_zone $binary_remote_addr zone=api:10m rate=60r/m;
limit_req_zone $binary_remote_addr zone=auth:10m rate=10r/m;

# Security headers
add_header X-Frame-Options "SAMEORIGIN";
add_header X-Content-Type-Options "nosniff";
add_header X-XSS-Protection "1; mode=block";
add_header Referrer-Policy "strict-origin-when-cross-origin";
add_header Content-Security-Policy "default-src 'self'; ...";
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains";
```

### 2. Application Layer (Laravel)

- **Authentication:** Laravel Sanctum with stateful cookie sessions.
- **Authorization:** Spatie Permission + Laravel Policies.
- **Input validation:** All inputs pass through Form Request classes before controller logic.
- **Mass assignment:** All Eloquent models define `$fillable` explicitly.
- **Query binding:** No raw SQL with user input. Eloquent query builder only.
- **Password hashing:** `bcrypt` via Laravel's `Hash` facade. Never store plaintext.
- **Rate limiting:** Laravel `ThrottleRequests` middleware on auth routes.

### 3. Data Layer (MySQL)

- Production database user has minimum required privileges (no `DROP`, `CREATE`).
- Database credentials never in application code — only via `.env` (excluded from git).
- All foreign keys have indexes.
- PII fields (phone, address) stored in application DB; no third-party analytics access.

### 4. Cookie & Session Security

```php
// Minimum session configuration for production
'secure'    => env('SESSION_SECURE_COOKIE', true),
'http_only' => true,
'same_site' => 'lax',
'lifetime'  => 120, // minutes
```

- All session cookies: `HttpOnly=true`, `Secure=true` (HTTPS only), `SameSite=Lax`.
- Frontend JavaScript must never read session tokens.
- No authentication credentials in `localStorage` or URL parameters.

---

## Payment Security

- Gateway API keys stored in environment variables only.
- Payment amounts verified server-to-server (never trust browser-submitted amounts).
- All incoming gateway webhooks verified via HMAC signature.
- Processed webhook event IDs stored to `payment_webhook_events` for idempotency.
- No gateway credentials baked into Docker images.

---

## Admin Security

- Admin accounts require a dedicated login with separate rate limiting.
- All admin routes protected by `auth:sanctum` + permission middleware.
- Admin actions (login, role changes, order actions) written to `audit_logs`.
- `super_admin` role assignable only by existing `super_admin`.
- Privilege escalation prevention: a user cannot grant higher role than their own.

---

## File Upload Security

- Allowed MIME types: `image/jpeg`, `image/png`, `image/webp`.
- File size limit: 5MB per upload.
- Uploaded files served from object storage (never executed by the web server).
- File names randomized (UUID-based) to prevent path traversal.
- Virus/malware scanning recommended for production (via ClamAV or cloud service).

---

## Environment & Secrets

- `.env` files **never committed to git** (enforced via `.gitignore`).
- All secrets injected at runtime via environment variables or Docker secrets.
- `.env.example`, `.env.development.example`, `.env.production.example` contain placeholder values only.
- CI/CD secrets stored in GitHub Secrets (encrypted at rest).

---

## HTTPS & Transport Security

- All production traffic served over HTTPS.
- HTTP to HTTPS redirect enforced at NGINX level.
- HSTS header set with `max-age=31536000; includeSubDomains`.
- TLS certificates managed via Let's Encrypt or cloud provider.
- Minimum TLS version: TLS 1.2 (TLS 1.3 preferred).

---

## Logging & Monitoring

- Structured JSON logs for all API requests (Laravel).
- Failed authentication attempts logged with IP address.
- Payment webhook errors logged and alerted.
- Admin action audit trail maintained in `audit_logs`.
- Sensitive data (passwords, tokens, raw payment responses) never logged in plaintext.

---

## Security Testing Checklist (Phase 11)

- [ ] OWASP Top 10 review
- [ ] SQL injection tests on all filter/search parameters
- [ ] Authentication bypass attempts
- [ ] CSRF token validation tests
- [ ] Rate limiting effectiveness tests
- [ ] Payment webhook signature bypass attempts
- [ ] Privilege escalation tests (customer accessing admin APIs)
- [ ] File upload malicious file tests
- [ ] XSS in product descriptions and user inputs
- [ ] Sensitive data in HTTP responses (no password fields, no raw tokens)
