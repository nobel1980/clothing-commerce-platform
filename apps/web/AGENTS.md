# Web Application Rules (`apps/web/AGENTS.md`)

This directory contains the Next.js 16 frontend application, serving both the public Customer Storefront and the internal Admin Dashboard.

---

## 1. Stack & Architecture Standards

* **Framework:** Next.js 16 (App Router, Server Components by default).
* **Language:** TypeScript (Strict mode enabled).
* **Styling & UI:** Tailwind CSS, `shadcn/ui`, `lucide-react`.
* **State Management:**
  * **Server State / API Data:** TanStack Query (`@tanstack/react-query`) or Server Actions / RSC fetching.
  * **Client/Interaction State:** Zustand (for client-only UI states like slide-over cart, filter states).
* **Form & Validation:** `react-hook-form` paired with `zod`.

---

## 2. Page & Directory Boundaries

```
apps/web/src/
├── app/
│   ├── (store)/         # Customer Storefront (SSR / ISR / Public Caching)
│   ├── (admin)/         # Central Admin Dashboard (Dynamic / Authenticated)
│   ├── login/
│   └── register/
├── features/            # Feature-driven UI components & custom hooks
├── components/          # Reusable atomic UI elements (shadcn/ui)
└── lib/                 # API client, Sanctum auth wrappers, utility functions
```

---

## 3. Critical Frontend Rules

1. **Server Components First:**
   * Default to React Server Components (`RSC`). Use `'use client'` only when interactive state, browser APIs, or event listeners are required.
2. **Pricing & Totals Guardrail:**
   * NEVER calculate order subtotal, discounts, shipping fees, or final totals on the client side. Always display totals fetched from the backend `/checkout/quote` API endpoint.
3. **Authentication & Session:**
   * Customer and Admin authentication relies on **Laravel Sanctum cookie-based sessions**.
   * Do NOT store bearer tokens, user passwords, or sensitive session data in `localStorage` or `sessionStorage`.
4. **Caching & Security:**
   * Never cache private customer pages (e.g., `/account`, `/checkout`, `/admin/*`) using public CDN caching headers.
   * Storefront product catalog pages must support cache invalidation when backend updates occur.