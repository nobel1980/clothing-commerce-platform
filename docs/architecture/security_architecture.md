# Security Architecture Record

## 1. Authentication Architecture

* **Customer & Admin Authentication:** Handled via **Laravel Sanctum** using stateful HTTP-Only, Secure, SameSite cookies for top-level domain applications (`meherfashion.com`, `api.meherfashion.com`).
* **Session Token Storage:** Browser local storage (`localStorage`/`sessionStorage`) MUST NOT be used for session tokens or sensitive authentication credentials.
* **API Rate Limiting:**
  * Public APIs: 60 requests/minute.
  * Auth Endpoints (`/auth/login`, `/auth/register`): 5 requests/minute per IP.
  * Checkout Endpoints: 10 requests/minute per authenticated user/IP.

---

## 2. Authorization & RBAC

Admin permissions follow a granular Role-Based Access Control model powered by Spatie Permission (`spatie/laravel-permission`).

```
Super Admin
   ├── Product Manager     (Catalog, Brands, Categories)
   ├── Inventory Manager   (Warehouses, Stock Adjustments)
   ├── Order Manager       (Orders, Shipping, Consignments)
   ├── Customer Support    (Customer Accounts, Reviews, Read-Only Orders)
   └── Marketing Manager   (Promotions, Coupons, Campaigns)
```

* Every admin controller action MUST check policy authorization or permission middleware (e.g., `$this->authorize('update', $product)`).

---

## 3. Operational Security Rules

1. **CSRF Protection:** Enabled on all state-mutating HTTP requests (`POST`, `PUT`, `PATCH`, `DELETE`).
2. **SQL Injection Prevention:** Enforced by strict usage of Eloquent ORM or parameterized query bindings. Raw SQL strings with concatenated user input are prohibited.
3. **XSS Protection:** Output sanitization on user-submitted html inputs (e.g., product reviews, customer names).
4. **Data Privacy:** Sensitive customer data (phone numbers, full delivery addresses, order history) must never be returned in unauthenticated public endpoints.
5. **Gateway Secret Management:** API credentials for bKash, Nagad, Pathao, etc., must strictly be loaded via environment variables (`.env`) and NEVER committed to version control.