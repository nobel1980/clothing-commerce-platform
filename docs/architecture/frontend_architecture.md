# Frontend Architecture Record

## 1. Application Layout & Route Boundaries

The Next.js 16 application in `apps/web/` utilizes App Router Route Groups to cleanly isolate presentation and security contexts between the customer storefront and the admin dashboard.

```
apps/web/src/app/
├── (store)/                 # Customer Storefront Group
│   ├── page.tsx             # Homepage
│   ├── products/            # Product Listing & Detail [slug]
│   ├── categories/          # Category pages
│   ├── cart/                # Shopping Cart
│   ├── checkout/            # Checkout Workflow
│   └── account/             # Customer Profile & Orders
│
├── (admin)/                 # Central Admin Dashboard Group
│   └── admin/
│       ├── dashboard/       # Sales Metrics & Overview
│       ├── products/        # Product & Variant Management
│       ├── inventory/       # Stock Adjustments & Alerts
│       ├── orders/          # Fulfillment & Status Updates
│       └── settings/        # System Configuration
│
└── layout.tsx               # Root Layout
```

---

## 2. Rendering & Caching Strategy

| Page Type | Rendering Strategy | Caching Behavior |
| :--- | :--- | :--- |
| **Homepage / Landing** | Server-Side Rendering (SSR) | Revalidated on catalog updates (ISR/Tag Invalidation). |
| **Product Listings / Details** | SSR / Incremental Static | Cache invalidated via webhooks/events when product data changes. |
| **Search / Filtering** | Dynamic SSR | Query parameters directly drive server fetching. |
| **Cart / Checkout** | Dynamic Client Components | No public CDN caching; interactive state managed client-side. |
| **Customer Account** | Dynamic Private SSR | Authenticated via Sanctum cookies; strictly uncached. |
| **Admin Dashboard** | Dynamic Private SSR | Uncached; requires valid admin permissions. |

---

## 3. Client State & Form Management Rules

1. **Server vs. Client State:**
   * Do NOT sync backend database state into Zustand stores.
   * API state must be managed strictly using TanStack Query or Server Components data fetching.
2. **Zustand Scope:**
   * Zustand is restricted to UI-only interaction states (e.g., slide-over cart drawer toggle, mobile navigation drawer, theme preferences).
3. **Forms & Schema Validation:**
   * All user input forms (checkout form, login, admin product creation) MUST use `react-hook-form` bound to `zod` schemas.
   * Client-side validation schemas should mirror the backend FormRequest validation constraints.