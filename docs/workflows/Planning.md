# Clothing Commerce Platform — Software Architecture & Implementation Blueprint



Architecture baseline

Single-brand commerce

Bangladesh market



Based on your decisions, I recommend building a single-brand, single-store, modular monolith with a monorepo structure, designed to support a growing clothing business with approximately 1,000–20,000 products.

The platform will have:

- One centralized administration dashboard.
- One customer-facing storefront.
- One Laravel backend API.
- One MySQL database.
- Redis for caching, queues and distributed locks.
- Bangladesh-focused payments, COD and courier integrations.
- Clear domain boundaries for future microservice extraction.

The most important architectural decision is to avoid designing a multi-vendor marketplace. You don't need vendor onboarding, vendor commissions, vendor settlements or tenant isolation. Those would add unnecessary complexity.

Instead, build a strong commerce core that supports product variants, inventory, orders, payments, promotions, shipping and customer management.

# 1. Architecture decisions

## 1.1 Architecture overview

# Customer Storefront

Next.js 16 · App Router · TypeScript

SEO

SSR

Product catalog

Checkout

# Central Admin Dashboard

Next.js 16 · Role-based access

Products · Inventory · Orders · Customers · Reports

REST API · /api/v1

# Laravel 13 API

Modular Monolith · Business Logic · Authorization

Identity

Catalog

Inventory

Cart

Checkout

Orders

Payments

Promotions

Shipping

Customers

Reviews

Notifications

MySQL

Primary DB

Redis

Cache / Queue

Storage

Product media

Bangladesh integrations

bKash · Nagad · Cards · COD · Pathao · Steadfast · RedX

## 1.2 Technology stack

| Component  | Technology               | Decision                      |
| ---------- | ------------------------ | ----------------------------- |
| Storefront | Next.js 16               | Required                      |
| Admin      | Next.js 16               | Same frontend application     |
| Language   | TypeScript               | Strict mode                   |
| UI         | Tailwind CSS + shadcn/ui | Recommended                   |
| Backend    | Laravel 13               | Recommended baseline          |
| PHP        | PHP 8.3+                 | Compatible baseline           |
| API        | REST + JSON              | Versioned                     |
| Database   | MySQL 8                  | Primary database              |
| Cache      | Redis                    | Cache and locks               |
| Queue      | Laravel Queue + Redis    | Async processing              |
| Auth       | Laravel Sanctum          | Customer/admin authentication |
| RBAC       | Spatie Permission        | Admin authorization           |
| API docs   | OpenAPI                  | Contract documentation        |
| Testing    | Pest + PHPUnit + Vitest  | Backend/frontend              |
| E2E        | Playwright               | Purchase journey              |
| Containers | Docker Compose           | Local development             |
| CI/CD      | GitHub Actions           | Automated deployment          |
| Monitoring | Laravel logs initially   | Expand later                  |

For a new project, I would use Laravel 13 rather than Laravel 11. Pin exact framework and package versions in Composer and lockfiles after verifying compatibility.

## 1.3 Important architectural decisions

| Decision             | Selected approach      |
| -------------------- | ---------------------- |
| Business model       | Single brand           |
| Storefronts          | One                    |
| Admin applications   | One                    |
| Backend applications | One                    |
| Database             | One MySQL database     |
| Database ownership   | Module-level ownership |
| Inventory            | SKU/variant-based      |
| Currency             | BDT initially          |
| Tax                  | Configurable           |
| Shipping             | Provider adapters      |
| Payment              | Gateway adapters       |
| Multi-tenancy        | Not required           |
| Event architecture   | Domain events + Outbox |
| Microservices        | Future extraction      |

# 2. Monorepo repository structure

Repository name:

`clothing-commerce-platform`

I recommend keeping the storefront and admin in the same Next.js application initially. They can have separate route groups, layouts, navigation, authorization and component boundaries.

This avoids maintaining two frontend deployments unnecessarily.

```

clothing-commerce-platform/
│
├── apps/
│   ├── web/                            # Next.js 16
│   │   ├── src/
│   │   │   ├── app/
│   │   │   │   ├── (store)/
│   │   │   │   │   ├── page.tsx
│   │   │   │   │   ├── products/
│   │   │   │   │   │   └── [slug]/
│   │   │   │   │   ├── categories/
│   │   │   │   │   ├── search/
│   │   │   │   │   ├── cart/
│   │   │   │   │   ├── checkout/
│   │   │   │   │   └── account/
│   │   │   │   │
│   │   │   │   ├── (admin)/
│   │   │   │   │   └── admin/
│   │   │   │   │       ├── dashboard/
│   │   │   │   │       ├── products/
│   │   │   │   │       ├── categories/
│   │   │   │   │       ├── inventory/
│   │   │   │   │       ├── orders/
│   │   │   │   │       ├── customers/
│   │   │   │   │       ├── promotions/
│   │   │   │   │       ├── shipping/
│   │   │   │   │       ├── reports/
│   │   │   │   │       └── settings/
│   │   │   │   │
│   │   │   │   ├── login/
│   │   │   │   ├── register/
│   │   │   │   ├── api/                 # BFF/webhooks if needed
│   │   │   │   ├── layout.tsx
│   │   │   │   └── globals.css
│   │   │   │
│   │   │   ├── features/
│   │   │   │   ├── catalog/
│   │   │   │   ├── cart/
│   │   │   │   ├── checkout/
│   │   │   │   ├── orders/
│   │   │   │   ├── customers/
│   │   │   │   └── admin/
│   │   │   │
│   │   │   ├── components/
│   │   │   │   ├── ui/
│   │   │   │   ├── layout/
│   │   │   │   └── shared/
│   │   │   │
│   │   │   ├── lib/
│   │   │   │   ├── api/
│   │   │   │   ├── auth/
│   │   │   │   └── utils/
│   │   │   ├── hooks/
│   │   │   ├── stores/
│   │   │   └── types/
│   │   ├── public/
│   │   └── package.json
│   │
│   └── api/                             # Laravel 13
│       ├── app/
│       │   ├── Modules/
│       │   │   ├── Identity/
│       │   │   ├── Catalog/
│       │   │   ├── Inventory/
│       │   │   ├── Cart/
│       │   │   ├── Checkout/
│       │   │   ├── Orders/
│       │   │   ├── Payments/
│       │   │   ├── Promotions/
│       │   │   ├── Shipping/
│       │   │   ├── Customers/
│       │   │   ├── Reviews/
│       │   │   ├── Notifications/
│       │   │   └── Reporting/
│       │   │
│       │   ├── Shared/
│       │   │   ├── Contracts/
│       │   │   ├── Events/
│       │   │   ├── Exceptions/
│       │   │   ├── Money/
│       │   │   └── Support/
│       │   │
│       │   └── Providers/
│       │
│       ├── bootstrap/
│       ├── config/
│       ├── database/
│       ├── routes/
│       ├── storage/
│       ├── tests/
│       └── composer.json
│
├── packages/
│   ├── ui/                              # Shared UI
│   ├── types/                           # Shared TS contracts
│   ├── eslint-config/
│   └── tsconfig/
│
├── infrastructure/
│   ├── docker/
│   ├── nginx/
│   ├── mysql/
│   ├── redis/
│   └── monitoring/
│
├── docs/
│   ├── architecture/
│   ├── database/
│   ├── api/
│   ├── workflows/
│   ├── security/
│   ├── deployment/
│   └── adr/
│
├── scripts/
├── .github/
│   └── workflows/
│
├── docker-compose.yml
├── pnpm-workspace.yaml
├── package.json
├── AGENTS.md
├── .env.example
├── .gitignore
└── README.md

```

Monorepo decision: pnpm workspaces manage the JavaScript workspace. Composer independently manages Laravel dependencies. Do not attempt to make pnpm manage PHP dependencies.

A shared TypeScript package should contain frontend contracts and enums, not PHP business rules. Ideally generate API types from OpenAPI rather than manually maintaining two copies of the same API definitions.

# 3. Backend domain architecture

I recommend a pragmatic Domain-Driven Design (DDD) approach. Use business modules, but don't over-engineer every CRUD operation with repositories, factories, interfaces and abstractions.

The Laravel application should follow this structure:

```
apps/api/app/Modules/Catalog/
│
├── Domain/
│   ├── Models/
│   ├── Enums/
│   ├── Events/
│   ├── Exceptions/
│   ├── Contracts/
│   └── ValueObjects/
│
├── Application/
│   ├── Actions/
│   ├── DTOs/
│   ├── Queries/
│   └── Listeners/
│
├── Infrastructure/
│   ├── Persistence/
│   ├── Integrations/
│   └── Repositories/
│
└── Presentation/
    ├── Http/
    │   ├── Controllers/
    │   ├── Requests/
    │   └── Resources/
    └── Routes/
```

For simple operations, a controller can call an Action directly. Introduce domain abstractions when they solve a real problem.

## 3.1 Domain responsibility matrix

| Domain        | Owns                                                                    | Must not own                |
| ------------- | ----------------------------------------------------------------------- | --------------------------- |
| Identity      | Users, roles, permissions, sessions                                     | Product business rules      |
| Catalog       | Products, categories, attributes, variants, images                      | Stock movements             |
| Inventory     | Stock, reservations, adjustments, warehouses                            | Product descriptions        |
| Cart          | Cart, cart items, quantity selection                                    | Final payment               |
| Checkout      | Pricing orchestration, address validation, order placement coordination | Gateway implementation      |
| Orders        | Order lifecycle, items, status history                                  | Gateway credentials         |
| Payments      | Transactions, verification, refunds                                     | Order fulfilment            |
| Promotions    | Coupons, discount rules, usage limits                                   | Payment processing          |
| Shipping      | Courier adapters, shipments, tracking                                   | Product inventory           |
| Customers     | Customer profiles, addresses, wishlist                                  | Admin permission management |
| Reviews       | Product reviews and moderation                                          | Product catalog management  |
| Notifications | Email, SMS, notifications                                               | Order state decisions       |
| Reporting     | Read models, sales summaries                                            | Writing transactional data  |

### Cross-domain communication

Example: customer places an order.

```
Checkout
   |
   +----> Catalog: Validate product and variant
   |
   +----> Promotions: Calculate eligible discount
   |
   +----> Inventory: Reserve stock
   |
   +----> Orders: Create order
   |
   +----> Payments: Initiate payment
   |
   +----> Outbox: Record domain event
```

Checkout orchestrates the process. It does not duplicate the business logic of all the other modules.

### Module communication rules

- Synchronous communication: application service interfaces.
- Asynchronous communication: domain events.
- External communication: integration adapters.
- Cross-module database writes: prohibited.
- Cross-module reads: use explicitly defined application queries.

For example, the Inventory module may expose:

```
interface InventoryService
{
    public function reserve(
        array $items,
        string $reference
    ): ReservationResult;

    public function release(string $reference): void;

    public function confirm(string $reference): void;
}
```

The implementation stays inside Inventory.

# 4. Database architecture and ERD

This is the foundation of your e-commerce system.

I would start with one MySQL database named:

`clothing_commerce`

Use InnoDB, foreign keys, appropriate unique constraints, and explicit database indexes.

## 4.1 Database module ownership

| Domain        | Tables                                                                                        |
| ------------- | --------------------------------------------------------------------------------------------- |
| Identity      | users, roles, permissions, model_has_roles, role_has_permissions                              |
| Catalog       | brands, categories, products, product_images, product_variants                                |
| Attributes    | attributes, attribute_values, variant_attribute_values                                        |
| Inventory     | warehouses, inventory_items, inventory_movements, stock_reservations, stock_reservation_items |
| Cart          | carts, cart_items                                                                             |
| Customers     | customer_addresses, wishlists, wishlist_items                                                 |
| Promotions    | coupons, coupon_rules, coupon_usages                                                          |
| Orders        | orders, order_items, order_status_histories                                                   |
| Payments      | payment_transactions, payment_webhook_events, refunds                                         |
| Shipping      | shipping_zones, shipping_methods, shipments, shipment_tracking_events                         |
| Reviews       | reviews                                                                                       |
| Notifications | notification_logs                                                                             |
| Platform      | outbox_events, idempotency_keys, audit_logs                                                   |

## 4.2 Core entity relationship diagram

The following ERD shows the primary relationships. It is a conceptual starting point; the final migrations should include the additional operational tables listed above.

\#chatgpt-mermaid-\_r_k8\_{font-family:-apple-system-body,ui-sans-serif,-apple-system,system-ui,"Segoe UI",Helvetica,"Apple Color Emoji",Arial,sans-serif,"Segoe UI Emoji","Segoe UI Symbol";font-size:16px;fill:rgb(13, 13, 13);}@keyframes edge-animation-frame{from{stroke-dashoffset:0;}}@keyframes dash{to{stroke-dashoffset:0;}}#chatgpt-mermaid-\_r_k8\_ .edge-animation-slow{stroke-dasharray:9,5!important;stroke-dashoffset:900;animation:dash 50s linear infinite;stroke-linecap:round;}#chatgpt-mermaid-\_r_k8\_ .edge-animation-fast{stroke-dasharray:9,5!important;stroke-dashoffset:900;animation:dash 20s linear infinite;stroke-linecap:round;}#chatgpt-mermaid-\_r_k8\_ .error-icon{fill:rgb(243, 243, 243);}#chatgpt-mermaid-\_r_k8\_ .error-text{fill:rgb(13, 13, 13);stroke:rgb(13, 13, 13);}#chatgpt-mermaid-\_r_k8\_ .edge-thickness-normal{stroke-width:1px;}#chatgpt-mermaid-\_r_k8\_ .edge-thickness-thick{stroke-width:3.5px;}#chatgpt-mermaid-\_r_k8\_ .edge-pattern-solid{stroke-dasharray:0;}#chatgpt-mermaid-\_r_k8\_ .edge-thickness-invisible{stroke-width:0;fill:none;}#chatgpt-mermaid-\_r_k8\_ .edge-pattern-dashed{stroke-dasharray:3;}#chatgpt-mermaid-\_r_k8\_ .edge-pattern-dotted{stroke-dasharray:2;}#chatgpt-mermaid-\_r_k8\_ .marker{fill:rgb(143, 143, 143);stroke:rgb(143, 143, 143);}#chatgpt-mermaid-\_r_k8\_ .marker.cross{stroke:rgb(143, 143, 143);}#chatgpt-mermaid-\_r_k8\_ svg{font-family:-apple-system-body,ui-sans-serif,-apple-system,system-ui,"Segoe UI",Helvetica,"Apple Color Emoji",Arial,sans-serif,"Segoe UI Emoji","Segoe UI Symbol";font-size:16px;}#chatgpt-mermaid-\_r_k8\_ p{margin:0;}#chatgpt-mermaid-\_r_k8\_ .entityBox{fill:rgb(222, 234, 251);stroke:rgb(83, 154, 248);}#chatgpt-mermaid-\_r_k8\_ .relationshipLabelBox{fill:rgb(243, 243, 243);opacity:0.7;background-color:rgb(243, 243, 243);}#chatgpt-mermaid-\_r_k8\_ .relationshipLabelBox rect{opacity:0.5;}#chatgpt-mermaid-\_r_k8\_ .labelBkg{background-color:rgba(243, 243, 243, 0.5);}#chatgpt-mermaid-\_r_k8\_ .edgeLabel{background-color:rgb(252, 252, 252);}#chatgpt-mermaid-\_r_k8\_ .edgeLabel .label rect{fill:rgb(252, 252, 252);}#chatgpt-mermaid-\_r_k8\_ .edgeLabel .label text{fill:rgb(13, 13, 13);}#chatgpt-mermaid-\_r_k8\_ .edgeLabel .label{fill:rgb(83, 154, 248);font-size:14px;}#chatgpt-mermaid-\_r_k8\_ .label{font-family:-apple-system-body,ui-sans-serif,-apple-system,system-ui,"Segoe UI",Helvetica,"Apple Color Emoji",Arial,sans-serif,"Segoe UI Emoji","Segoe UI Symbol";color:rgb(13, 13, 13);}#chatgpt-mermaid-\_r_k8\_ .edge-pattern-dashed{stroke-dasharray:8,8;}#chatgpt-mermaid-\_r_k8\_ .node rect,#chatgpt-mermaid-\_r_k8\_ .node circle,#chatgpt-mermaid-\_r_k8\_ .node ellipse,#chatgpt-mermaid-\_r_k8\_ .node polygon{fill:rgb(222, 234, 251);stroke:rgb(83, 154, 248);stroke-width:1px;}#chatgpt-mermaid-\_r_k8\_ .relationshipLine{stroke:rgb(143, 143, 143);stroke-width:1px;fill:none;}#chatgpt-mermaid-\_r_k8\_ .marker{fill:none!important;stroke:rgb(143, 143, 143)!important;stroke-width:1;}#chatgpt-mermaid-\_r_k8\_ [data-look=neo].labelBkg{background-color:rgba(243, 243, 243, 0.5);}#chatgpt-mermaid-\_r_k8\_ .node .neo-node{stroke:rgb(83, 154, 248);}#chatgpt-mermaid-\_r_k8\_ [data-look="neo"].node rect,#chatgpt-mermaid-\_r_k8\_ [data-look="neo"].cluster rect,#chatgpt-mermaid-\_r_k8\_ [data-look="neo"].node polygon{stroke:url(#chatgpt-mermaid-\_r_k8\_-gradient);filter:drop-shadow( 1px 2px 2px rgba(185,185,185,1));}#chatgpt-mermaid-\_r_k8\_ [data-look="neo"].swimlane.cluster rect{filter:none;}#chatgpt-mermaid-\_r_k8\_ [data-look="neo"].node path{stroke:url(#chatgpt-mermaid-\_r_k8\_-gradient);stroke-width:1px;}#chatgpt-mermaid-\_r_k8\_ [data-look="neo"].node .outer-path{filter:drop-shadow( 1px 2px 2px rgba(185,185,185,1));}#chatgpt-mermaid-\_r_k8\_ [data-look="neo"].node .neo-line path{stroke:rgb(83, 154, 248);filter:none;}#chatgpt-mermaid-\_r_k8\_ [data-look="neo"].node circle{stroke:url(#chatgpt-mermaid-\_r_k8\_-gradient);filter:drop-shadow( 1px 2px 2px rgba(185,185,185,1));}#chatgpt-mermaid-\_r_k8\_ [data-look="neo"].node circle .state-start{fill:#000000;}#chatgpt-mermaid-\_r_k8\_ [data-look="neo"].icon-shape .icon{fill:url(#chatgpt-mermaid-\_r_k8\_-gradient);filter:drop-shadow( 1px 2px 2px rgba(185,185,185,1));}#chatgpt-mermaid-\_r_k8\_ [data-look="neo"].icon-shape .icon-neo path{stroke:url(#chatgpt-mermaid-\_r_k8\_-gradient);filter:drop-shadow( 1px 2px 2px rgba(185,185,185,1));}#chatgpt-mermaid-\_r_k8\_ :root{--mermaid-font-family:-apple-system-body,ui-sans-serif,-apple-system,system-ui,"Segoe UI",Helvetica,"Apple Color Emoji",Arial,sans-serif,"Segoe UI Emoji","Segoe UI Symbol";}USERSCUSTOMER_ADDRESSESCARTSORDERSCATEGORIESPRODUCTSPRODUCT_IMAGESPRODUCT_VARIANTSATTRIBUTESATTRIBUTE_VALUESVARIANT_ATTRIBUTE_VALUESWAREHOUSESINVENTORY_ITEMSCART_ITEMSORDER_ITEMSPAYMENT_TRANSACTIONSSHIPMENTSORDER_STATUS_HISTORIESCOUPONSCOUPON_USAGESREVIEWSINVENTORY_MOVEMENTSownsownsplacescontainshashasdefinesmapsselectedstoresstockedcontainsselectedcontainsreferencedpaid_byfulfilled_bytracksredeemedwritesreceivesrecords

## 4.3 Important database design details

### A. Products and variants

`products`

| Column       | Type               | Description           |
| ------------ | ------------------ | --------------------- |
| id           | BIGINT             | Primary key           |
| category_id  | BIGINT             | Category              |
| brand_id     | BIGINT nullable    | Brand                 |
| name         | VARCHAR(255)       | Product name          |
| slug         | VARCHAR(255)       | SEO URL               |
| description  | TEXT               | Product description   |
| status       | VARCHAR(30)        | draft/active/archived |
| is_featured  | BOOLEAN            | Featured              |
| published_at | TIMESTAMP nullable | Publication date      |

Unique index: `slug`.

`product_variants`

| Column           | Type                   | Description     |
| ---------------- | ---------------------- | --------------- |
| id               | BIGINT                 | Primary key     |
| product_id       | BIGINT                 | Parent product  |
| sku              | VARCHAR(100)           | Unique SKU      |
| barcode          | VARCHAR(100) nullable  | Barcode         |
| color            | VARCHAR(100) nullable  | Display color   |
| size             | VARCHAR(50) nullable   | Display size    |
| price            | DECIMAL(12,2)          | Selling price   |
| compare_at_price | DECIMAL(12,2) nullable | Original price  |
| cost_price       | DECIMAL(12,2) nullable | Internal cost   |
| status           | VARCHAR(30)            | active/inactive |

For the initial release, keeping color and size directly on the variant can simplify implementation. Add the normalized attribute-value mapping when you need additional attributes such as fabric, fit, sleeve type or pattern.

Important: SKU must be unique. A product can have multiple variants, but each sellable combination should have its own stock record.

### B. Inventory

`inventory_items`

```
id
variant_id
warehouse_id
quantity_on_hand
quantity_reserved
reorder_level
created_at
updated_at
```

Available stock:

\\[ Available = OnHand - Reserved \\]

Example:

| SKU       | On hand | Reserved | Available |
| --------- | ------- | -------- | --------- |
| TSH-BLK-M | 50      | 5        | 45        |
| TSH-BLK-L | 30      | 10       | 20        |
| TSH-WHT-M | 15      | 2        | 13        |

Add unique constraint:

`UNIQUE(variant_id, warehouse_id)`

Never allow stock updates without recording an inventory movement.

### C. Orders

`orders`

```
id
order_number
user_id
shipping_address_snapshot
billing_address_snapshot
subtotal
discount_amount
shipping_amount
tax_amount
total_amount
currency
order_status
payment_status
fulfillment_status
coupon_code
customer_note
placed_at
created_at
updated_at
```

`order_items`

```
id
order_id
product_id
variant_id
product_name
variant_name
sku
quantity
unit_price
discount_amount
line_total
product_image
created_at
```

Keep snapshots so that historical orders remain accurate even if the customer or product changes later.

For addresses, use structured snapshots containing the address, recipient, phone, district, area and delivery instructions.

### D. Money handling

For BDT, use `DECIMAL(12,2)` consistently and PHP decimal-safe calculations or integer poisha internally.

For example:

- ৳1,200.00 = 120,000 poisha.
- Never calculate money using PHP floating-point arithmetic.
- Save currency with transaction records.

### E. Additional database requirements

I would add the following constraints:

- `users.phone` normalized to a consistent Bangladesh phone format.
- `products.slug` unique.
- `product_variants.sku` unique.
- `orders.order_number` unique.
- Gateway transaction references unique.
- Webhook event IDs unique where provided by the gateway.
- Idempotency keys unique by customer and operation.
- Inventory movements append-only.
- Order items immutable after order placement, except explicitly audited adjustments.

# 5. Bangladesh-specific commerce design

Bangladesh requirements should be incorporated into the architecture, not added as last-minute integrations.

## 5.1 Address model

Do not store the complete address in one text field.

Recommended structure:

```
customer_addresses
├── id
├── user_id
├── recipient_name
├── phone
├── division
├── district
├── upazila
├── area
├── address_line
├── postal_code
├── delivery_instruction
├── address_type
└── is_default
```

You can later introduce normalized division/district/upazila lookup tables.

For checkout, preserve the submitted address snapshot on the order.

## 5.2 Payment architecture

Payment domain

Payment Gateway Interface

Initiate · Verify · Refund · Webhook handling

bKash

Adapter

Nagad

Adapter

Card Gateway

Adapter

COD

Internal method

Use an abstraction such as:

```
interface PaymentGateway
{
    public function initiate(
        PaymentRequest $request
    ): PaymentInitiationResult;

    public function verify(
        string $gatewayReference
    ): PaymentVerificationResult;

    public function refund(
        RefundRequest $request
    ): RefundResult;
}
```

Gateway implementations:

```
Payments/
├── Domain/
├── Application/
├── Infrastructure/
│   └── Gateways/
│       ├── Bkash/
│       ├── Nagad/
│       ├── SslCommerz/
│       └── Mock/
└── Presentation/
```

Do not assume every gateway offers identical refund or callback functionality. Implement each provider against its official merchant API and supported capabilities.

My recommendation is to integrate one gateway first, then add others behind the same interface.

## 5.3 Courier architecture

Potential integrations:

- Pathao Courier
- Steadfast Courier
- REDX

Shipping Service

Courier Provider Contract

Create parcel · Cancel · Track · Calculate cost

Pathao

Steadfast

REDX

Your Shipping module should handle:

- Inside Dhaka delivery.
- Outside Dhaka delivery.
- Delivery charges.
- COD collection.
- Courier consignment ID.
- Parcel tracking.
- Delivery success.
- Delivery failure.
- Return to merchant (RTM).
- Courier reconciliation.

Courier status and internal order status must be mapped separately. Different couriers may use different status names and callback formats.

## 5.4 Pricing engine

Create a centralized pricing service.

```
Product price
    +
Variant price adjustment
    -
Eligible discount
    -
Coupon discount
    +
Shipping charge
    +
Applicable tax
    =
Final order total
```

For the first release, variant `price` can be the final base selling price, with promotion discounts applied separately.

Do not let the frontend determine final pricing.

# 6. API contract design

All APIs should follow a consistent contract, use versioning and document their request/response schemas.

Base URL:

```
https://api.meherfashion.com.com/api/v1
```

For local development:

```
http://localhost:8000/api/v1
```

## 6.1 API module map

| Module           | Endpoint prefix     | Access                 |
| ---------------- | ------------------- | ---------------------- |
| Identity         | `/auth`             | Public / authenticated |
| Catalog          | `/catalog`          | Public                 |
| Cart             | `/cart`             | Guest / customer       |
| Customers        | `/customers`        | Customer               |
| Checkout         | `/checkout`         | Customer               |
| Orders           | `/orders`           | Customer               |
| Payments         | `/payments`         | Customer / gateway     |
| Shipping         | `/shipping`         | Admin / internal       |
| Reviews          | `/reviews`          | Customer / public      |
| Admin Catalog    | `/admin/products`   | Admin                  |
| Admin Inventory  | `/admin/inventory`  | Admin                  |
| Admin Orders     | `/admin/orders`     | Admin                  |
| Admin Customers  | `/admin/customers`  | Admin                  |
| Admin Promotions | `/admin/promotions` | Admin                  |
| Admin Reports    | `/admin/reports`    | Admin                  |

## 6.2 Customer-facing API contracts

| Method | Endpoint                     | Purpose            |
| ------ | ---------------------------- | ------------------ |
| POST   | `/auth/register`             | Register           |
| POST   | `/auth/login`                | Login              |
| POST   | `/auth/logout`               | Logout             |
| GET    | `/auth/me`                   | Current user       |
| GET    | `/catalog/categories`        | Categories         |
| GET    | `/catalog/products`          | Product listing    |
| GET    | `/catalog/products/{slug}`   | Product details    |
| GET    | `/catalog/products/featured` | Featured products  |
| GET    | `/cart`                      | Get cart           |
| POST   | `/cart/items`                | Add item           |
| PATCH  | `/cart/items/{id}`           | Update quantity    |
| DELETE | `/cart/items/{id}`           | Remove item        |
| POST   | `/checkout/quote`            | Calculate checkout |
| POST   | `/checkout/orders`           | Place order        |
| GET    | `/orders`                    | Customer orders    |
| GET    | `/orders/{orderNumber}`      | Order details      |
| POST   | `/payments/initiate`         | Start payment      |
| POST   | `/payments/verify`           | Verify payment     |
| GET    | `/customers/addresses`       | Customer addresses |
| POST   | `/customers/addresses`       | Add address        |

## 6.3 Admin API contracts

| Method | Endpoint                       | Purpose           |
| ------ | ------------------------------ | ----------------- |
| GET    | `/admin/dashboard`             | Dashboard metrics |
| POST   | `/admin/products`              | Create product    |
| PUT    | `/admin/products/{id}`         | Update product    |
| POST   | `/admin/products/{id}/publish` | Publish product   |
| POST   | `/admin/products/{id}/images`  | Upload images     |
| POST   | `/admin/categories`            | Create category   |
| POST   | `/admin/variants`              | Create variant    |
| GET    | `/admin/inventory`             | Stock overview    |
| POST   | `/admin/inventory/adjustments` | Adjust stock      |
| GET    | `/admin/orders`                | Manage orders     |
| POST   | `/admin/orders/{id}/confirm`   | Confirm order     |
| POST   | `/admin/orders/{id}/cancel`    | Cancel order      |
| POST   | `/admin/orders/{id}/ship`      | Create shipment   |
| GET    | `/admin/customers`             | Customer listing  |
| POST   | `/admin/promotions/coupons`    | Create coupon     |
| GET    | `/admin/reports/sales`         | Sales report      |

Use explicit actions for business transitions instead of allowing arbitrary changes to order status through generic `PUT` endpoints.

## 6.4 API response convention

Successful response:

```
{
  "success": true,
  "message": "Product retrieved successfully",
  "data": {
    "id": 101,
    "name": "Premium Cotton T-Shirt",
    "slug": "premium-cotton-t-shirt",
    "price": "1200.00",
    "currency": "BDT",
    "variants": [
      {
        "id": 501,
        "sku": "TSH-BLK-M",
        "color": "Black",
        "size": "M",
        "available_quantity": 15
      }
    ]
  },
  "meta": null
}
```

Validation error:

```
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "phone": [
      "The phone number is required."
    ]
  }
}
```

Use appropriate HTTP status codes:

- `200` successful retrieval or update.
- `201` created.
- `202` accepted for asynchronous processing.
- `204` successful deletion without a body.
- `401` unauthenticated.
- `403` unauthorized.
- `404` not found.
- `409` conflicting state or duplicate operation.
- `422` validation failure.
- `429` rate limit exceeded.

Use a consistent Laravel exception handler to normalize all errors.

## 6.5 Authentication architecture

I recommend Sanctum with cookie-based authentication if the Next.js frontend and Laravel API are deployed under the same top-level domain.

Example:

```
www.meherfashion.com.com
api.meherfashion.com.com
```

Configure:

- Sanctum stateful domains.
- Secure, HttpOnly cookies.
- Appropriate SameSite settings.
- HTTPS everywhere.
- CSRF protection.
- Session expiration.
- Login rate limiting.

For this architecture, the browser should use the Laravel session rather than storing bearer tokens in localStorage.

If you later introduce mobile apps or external clients, use Sanctum personal access tokens for those clients.

Admin authorization should use:

```
Super Admin
    |
    +--- Product Manager
    +--- Inventory Manager
    +--- Order Manager
    +--- Customer Support
    +--- Marketing Manager
    +--- Report Viewer
```

Although you have one admin dashboard, you should not give every admin user unrestricted permissions.

# 7. Critical business workflows

## 7.1 Product publishing workflow

\#chatgpt-mermaid-\_r_n9\_{font-family:-apple-system-body,ui-sans-serif,-apple-system,system-ui,"Segoe UI",Helvetica,"Apple Color Emoji",Arial,sans-serif,"Segoe UI Emoji","Segoe UI Symbol";font-size:16px;fill:rgb(13, 13, 13);}@keyframes edge-animation-frame{from{stroke-dashoffset:0;}}@keyframes dash{to{stroke-dashoffset:0;}}#chatgpt-mermaid-\_r_n9\_ .edge-animation-slow{stroke-dasharray:9,5!important;stroke-dashoffset:900;animation:dash 50s linear infinite;stroke-linecap:round;}#chatgpt-mermaid-\_r_n9\_ .edge-animation-fast{stroke-dasharray:9,5!important;stroke-dashoffset:900;animation:dash 20s linear infinite;stroke-linecap:round;}#chatgpt-mermaid-\_r_n9\_ .error-icon{fill:rgb(243, 243, 243);}#chatgpt-mermaid-\_r_n9\_ .error-text{fill:rgb(13, 13, 13);stroke:rgb(13, 13, 13);}#chatgpt-mermaid-\_r_n9\_ .edge-thickness-normal{stroke-width:1px;}#chatgpt-mermaid-\_r_n9\_ .edge-thickness-thick{stroke-width:3.5px;}#chatgpt-mermaid-\_r_n9\_ .edge-pattern-solid{stroke-dasharray:0;}#chatgpt-mermaid-\_r_n9\_ .edge-thickness-invisible{stroke-width:0;fill:none;}#chatgpt-mermaid-\_r_n9\_ .edge-pattern-dashed{stroke-dasharray:3;}#chatgpt-mermaid-\_r_n9\_ .edge-pattern-dotted{stroke-dasharray:2;}#chatgpt-mermaid-\_r_n9\_ .marker{fill:rgb(143, 143, 143);stroke:rgb(143, 143, 143);}#chatgpt-mermaid-\_r_n9\_ .marker.cross{stroke:rgb(143, 143, 143);}#chatgpt-mermaid-\_r_n9\_ svg{font-family:-apple-system-body,ui-sans-serif,-apple-system,system-ui,"Segoe UI",Helvetica,"Apple Color Emoji",Arial,sans-serif,"Segoe UI Emoji","Segoe UI Symbol";font-size:16px;}#chatgpt-mermaid-\_r_n9\_ p{margin:0;}#chatgpt-mermaid-\_r_n9\_ .label{font-family:-apple-system-body,ui-sans-serif,-apple-system,system-ui,"Segoe UI",Helvetica,"Apple Color Emoji",Arial,sans-serif,"Segoe UI Emoji","Segoe UI Symbol";color:rgb(13, 13, 13);}#chatgpt-mermaid-\_r_n9\_ .cluster-label text{fill:rgb(13, 13, 13);}#chatgpt-mermaid-\_r_n9\_ .cluster-label span{color:rgb(13, 13, 13);}#chatgpt-mermaid-\_r_n9\_ .cluster-label span p{background-color:transparent;}#chatgpt-mermaid-\_r_n9\_ .label text,#chatgpt-mermaid-\_r_n9\_ span{fill:rgb(13, 13, 13);color:rgb(13, 13, 13);}#chatgpt-mermaid-\_r_n9\_ .node rect,#chatgpt-mermaid-\_r_n9\_ .node circle,#chatgpt-mermaid-\_r_n9\_ .node ellipse,#chatgpt-mermaid-\_r_n9\_ .node polygon,#chatgpt-mermaid-\_r_n9\_ .node path{fill:rgb(222, 234, 251);stroke:rgb(83, 154, 248);stroke-width:1px;}#chatgpt-mermaid-\_r_n9\_ .rough-node .label text,#chatgpt-mermaid-\_r_n9\_ .node .label text,#chatgpt-mermaid-\_r_n9\_ .image-shape .label,#chatgpt-mermaid-\_r_n9\_ .icon-shape .label{text-anchor:middle;}#chatgpt-mermaid-\_r_n9\_ .node .katex path{fill:#000;stroke:#000;stroke-width:1px;}#chatgpt-mermaid-\_r_n9\_ .rough-node .label,#chatgpt-mermaid-\_r_n9\_ .node .label,#chatgpt-mermaid-\_r_n9\_ .image-shape .label,#chatgpt-mermaid-\_r_n9\_ .icon-shape .label{text-align:center;}#chatgpt-mermaid-\_r_n9\_ .node.clickable{cursor:pointer;}#chatgpt-mermaid-\_r_n9\_ .root .anchor path{fill:rgb(143, 143, 143)!important;stroke-width:0;stroke:rgb(143, 143, 143);}#chatgpt-mermaid-\_r_n9\_ .arrowheadPath{fill:rgb(143, 143, 143);}#chatgpt-mermaid-\_r_n9\_ .edgePath .path{stroke:rgb(143, 143, 143);stroke-width:1px;}#chatgpt-mermaid-\_r_n9\_ .flowchart-link{stroke:rgb(143, 143, 143);fill:none;}#chatgpt-mermaid-\_r_n9\_ .edgeLabel{background-color:rgb(252, 252, 252);text-align:center;}#chatgpt-mermaid-\_r_n9\_ .edgeLabel p{background-color:rgb(252, 252, 252);}#chatgpt-mermaid-\_r_n9\_ .edgeLabel rect{opacity:0.5;background-color:rgb(252, 252, 252);fill:rgb(252, 252, 252);}#chatgpt-mermaid-\_r_n9\_ .labelBkg{background-color:rgba(252, 252, 252, 0.5);}#chatgpt-mermaid-\_r_n9\_ .cluster rect{fill:rgb(243, 243, 243);stroke:rgba(0, 0, 0, 0.1);stroke-width:1px;}#chatgpt-mermaid-\_r_n9\_ .cluster text{fill:rgb(13, 13, 13);}#chatgpt-mermaid-\_r_n9\_ .cluster span{color:rgb(13, 13, 13);}#chatgpt-mermaid-\_r_n9\_ div.mermaidTooltip{position:absolute;text-align:center;max-width:200px;padding:2px;font-family:-apple-system-body,ui-sans-serif,-apple-system,system-ui,"Segoe UI",Helvetica,"Apple Color Emoji",Arial,sans-serif,"Segoe UI Emoji","Segoe UI Symbol";font-size:12px;background:rgb(243, 243, 243);border:1px solid rgba(0, 0, 0, 0.1);border-radius:2px;pointer-events:none;z-index:100;}#chatgpt-mermaid-\_r_n9\_ .flowchartTitleText{text-anchor:middle;font-size:18px;fill:rgb(13, 13, 13);}#chatgpt-mermaid-\_r_n9\_ rect.text{fill:none;stroke-width:0;}#chatgpt-mermaid-\_r_n9\_ .icon-shape,#chatgpt-mermaid-\_r_n9\_ .image-shape{background-color:rgb(252, 252, 252);text-align:center;}#chatgpt-mermaid-\_r_n9\_ .icon-shape p,#chatgpt-mermaid-\_r_n9\_ .image-shape p{background-color:rgb(252, 252, 252);padding:2px;}#chatgpt-mermaid-\_r_n9\_ .icon-shape .label rect,#chatgpt-mermaid-\_r_n9\_ .image-shape .label rect{opacity:0.5;background-color:rgb(252, 252, 252);fill:rgb(252, 252, 252);}#chatgpt-mermaid-\_r_n9\_ .label-icon{display:inline-block;height:1em;overflow:visible;vertical-align:-0.125em;}#chatgpt-mermaid-\_r_n9\_ .node .label-icon path{fill:currentColor;stroke:revert;stroke-width:revert;}#chatgpt-mermaid-\_r_n9\_ .node .neo-node{stroke:rgb(83, 154, 248);}#chatgpt-mermaid-\_r_n9\_ [data-look="neo"].node rect,#chatgpt-mermaid-\_r_n9\_ [data-look="neo"].cluster rect,#chatgpt-mermaid-\_r_n9\_ [data-look="neo"].node polygon{stroke:url(#chatgpt-mermaid-\_r_n9\_-gradient);filter:drop-shadow( 1px 2px 2px rgba(185,185,185,1));}#chatgpt-mermaid-\_r_n9\_ [data-look="neo"].swimlane.cluster rect{filter:none;}#chatgpt-mermaid-\_r_n9\_ [data-look="neo"].node path{stroke:url(#chatgpt-mermaid-\_r_n9\_-gradient);stroke-width:1px;}#chatgpt-mermaid-\_r_n9\_ [data-look="neo"].node .outer-path{filter:drop-shadow( 1px 2px 2px rgba(185,185,185,1));}#chatgpt-mermaid-\_r_n9\_ [data-look="neo"].node .neo-line path{stroke:rgb(83, 154, 248);filter:none;}#chatgpt-mermaid-\_r_n9\_ [data-look="neo"].node circle{stroke:url(#chatgpt-mermaid-\_r_n9\_-gradient);filter:drop-shadow( 1px 2px 2px rgba(185,185,185,1));}#chatgpt-mermaid-\_r_n9\_ [data-look="neo"].node circle .state-start{fill:#000000;}#chatgpt-mermaid-\_r_n9\_ [data-look="neo"].icon-shape .icon{fill:url(#chatgpt-mermaid-\_r_n9\_-gradient);filter:drop-shadow( 1px 2px 2px rgba(185,185,185,1));}#chatgpt-mermaid-\_r_n9\_ [data-look="neo"].icon-shape .icon-neo path{stroke:url(#chatgpt-mermaid-\_r_n9\_-gradient);filter:drop-shadow( 1px 2px 2px rgba(185,185,185,1));}#chatgpt-mermaid-\_r_n9\_ .node text{font-size:14px;font-weight:600;letter-spacing:normal;fill:rgb(0, 79, 153);}#chatgpt-mermaid-\_r_n9\_ .edgeLabels text{font-size:13px;font-weight:600;letter-spacing:-0.08px;fill:rgb(0, 79, 153);}#chatgpt-mermaid-\_r_n9\_ .node tspan[font-weight="normal"],#chatgpt-mermaid-\_r_n9\_ .edgeLabels tspan[font-weight="normal"]{font-weight:600;}#chatgpt-mermaid-\_r_n9\_ .edgeLabel .label rect{opacity:1;rx:13px;ry:13px;fill:rgb(245, 250, 255);stroke:rgb(206, 219, 229);stroke-width:1px;}#chatgpt-mermaid-\_r_n9\_ .node rect,#chatgpt-mermaid-\_r_n9\_ .node circle,#chatgpt-mermaid-\_r_n9\_ .node ellipse,#chatgpt-mermaid-\_r_n9\_ .node polygon,#chatgpt-mermaid-\_r_n9\_ .node path{fill:rgb(229, 243, 255);stroke:rgba(0, 0, 0, 0.1);stroke-width:1px;}#chatgpt-mermaid-\_r_n9\_ .node rect{rx:16px;ry:16px;}#chatgpt-mermaid-\_r_n9\_ .mermaid-decision .label-container{fill:rgb(245, 250, 255);stroke:rgb(206, 219, 229);stroke-dasharray:2,2;}#chatgpt-mermaid-\_r_n9\_ .edgePaths .flowchart-link{stroke:rgb(143, 143, 143);stroke-width:1px;stroke-linecap:round;stroke-linejoin:round;}#chatgpt-mermaid-\_r_n9\_ .marker{fill:rgb(143, 143, 143);stroke:rgb(143, 143, 143);}#chatgpt-mermaid-\_r_n9\_ :root{--mermaid-font-family:-apple-system-body,ui-sans-serif,-apple-system,system-ui,"Segoe UI",Helvetica,"Apple Color Emoji",Arial,sans-serif,"Segoe UI Emoji","Segoe UI Symbol";}Admin creates productAdd category and descriptionUpload product imagesCreate colors and sizesGenerate variant SKUsSet variant pricesConfigure inventoryValidation passed?Show errorsPublish productInvalidate catalog cacheProduct visible in storefrontNoYes

A product should not be published unless its required fields and sellable variant configuration are valid.

## 7.2 Checkout workflow

This is the most critical workflow in your platform.

\#chatgpt-mermaid-\_r_ng\_{font-family:-apple-system-body,ui-sans-serif,-apple-system,system-ui,"Segoe UI",Helvetica,"Apple Color Emoji",Arial,sans-serif,"Segoe UI Emoji","Segoe UI Symbol";font-size:16px;fill:rgb(13, 13, 13);}@keyframes edge-animation-frame{from{stroke-dashoffset:0;}}@keyframes dash{to{stroke-dashoffset:0;}}#chatgpt-mermaid-\_r_ng\_ .edge-animation-slow{stroke-dasharray:9,5!important;stroke-dashoffset:900;animation:dash 50s linear infinite;stroke-linecap:round;}#chatgpt-mermaid-\_r_ng\_ .edge-animation-fast{stroke-dasharray:9,5!important;stroke-dashoffset:900;animation:dash 20s linear infinite;stroke-linecap:round;}#chatgpt-mermaid-\_r_ng\_ .error-icon{fill:rgb(243, 243, 243);}#chatgpt-mermaid-\_r_ng\_ .error-text{fill:rgb(13, 13, 13);stroke:rgb(13, 13, 13);}#chatgpt-mermaid-\_r_ng\_ .edge-thickness-normal{stroke-width:1px;}#chatgpt-mermaid-\_r_ng\_ .edge-thickness-thick{stroke-width:3.5px;}#chatgpt-mermaid-\_r_ng\_ .edge-pattern-solid{stroke-dasharray:0;}#chatgpt-mermaid-\_r_ng\_ .edge-thickness-invisible{stroke-width:0;fill:none;}#chatgpt-mermaid-\_r_ng\_ .edge-pattern-dashed{stroke-dasharray:3;}#chatgpt-mermaid-\_r_ng\_ .edge-pattern-dotted{stroke-dasharray:2;}#chatgpt-mermaid-\_r_ng\_ .marker{fill:rgb(143, 143, 143);stroke:rgb(143, 143, 143);}#chatgpt-mermaid-\_r_ng\_ .marker.cross{stroke:rgb(143, 143, 143);}#chatgpt-mermaid-\_r_ng\_ svg{font-family:-apple-system-body,ui-sans-serif,-apple-system,system-ui,"Segoe UI",Helvetica,"Apple Color Emoji",Arial,sans-serif,"Segoe UI Emoji","Segoe UI Symbol";font-size:16px;}#chatgpt-mermaid-\_r_ng\_ p{margin:0;}#chatgpt-mermaid-\_r_ng\_ .label{font-family:-apple-system-body,ui-sans-serif,-apple-system,system-ui,"Segoe UI",Helvetica,"Apple Color Emoji",Arial,sans-serif,"Segoe UI Emoji","Segoe UI Symbol";color:rgb(13, 13, 13);}#chatgpt-mermaid-\_r_ng\_ .cluster-label text{fill:rgb(13, 13, 13);}#chatgpt-mermaid-\_r_ng\_ .cluster-label span{color:rgb(13, 13, 13);}#chatgpt-mermaid-\_r_ng\_ .cluster-label span p{background-color:transparent;}#chatgpt-mermaid-\_r_ng\_ .label text,#chatgpt-mermaid-\_r_ng\_ span{fill:rgb(13, 13, 13);color:rgb(13, 13, 13);}#chatgpt-mermaid-\_r_ng\_ .node rect,#chatgpt-mermaid-\_r_ng\_ .node circle,#chatgpt-mermaid-\_r_ng\_ .node ellipse,#chatgpt-mermaid-\_r_ng\_ .node polygon,#chatgpt-mermaid-\_r_ng\_ .node path{fill:rgb(222, 234, 251);stroke:rgb(83, 154, 248);stroke-width:1px;}#chatgpt-mermaid-\_r_ng\_ .rough-node .label text,#chatgpt-mermaid-\_r_ng\_ .node .label text,#chatgpt-mermaid-\_r_ng\_ .image-shape .label,#chatgpt-mermaid-\_r_ng\_ .icon-shape .label{text-anchor:middle;}#chatgpt-mermaid-\_r_ng\_ .node .katex path{fill:#000;stroke:#000;stroke-width:1px;}#chatgpt-mermaid-\_r_ng\_ .rough-node .label,#chatgpt-mermaid-\_r_ng\_ .node .label,#chatgpt-mermaid-\_r_ng\_ .image-shape .label,#chatgpt-mermaid-\_r_ng\_ .icon-shape .label{text-align:center;}#chatgpt-mermaid-\_r_ng\_ .node.clickable{cursor:pointer;}#chatgpt-mermaid-\_r_ng\_ .root .anchor path{fill:rgb(143, 143, 143)!important;stroke-width:0;stroke:rgb(143, 143, 143);}#chatgpt-mermaid-\_r_ng\_ .arrowheadPath{fill:rgb(143, 143, 143);}#chatgpt-mermaid-\_r_ng\_ .edgePath .path{stroke:rgb(143, 143, 143);stroke-width:1px;}#chatgpt-mermaid-\_r_ng\_ .flowchart-link{stroke:rgb(143, 143, 143);fill:none;}#chatgpt-mermaid-\_r_ng\_ .edgeLabel{background-color:rgb(252, 252, 252);text-align:center;}#chatgpt-mermaid-\_r_ng\_ .edgeLabel p{background-color:rgb(252, 252, 252);}#chatgpt-mermaid-\_r_ng\_ .edgeLabel rect{opacity:0.5;background-color:rgb(252, 252, 252);fill:rgb(252, 252, 252);}#chatgpt-mermaid-\_r_ng\_ .labelBkg{background-color:rgba(252, 252, 252, 0.5);}#chatgpt-mermaid-\_r_ng\_ .cluster rect{fill:rgb(243, 243, 243);stroke:rgba(0, 0, 0, 0.1);stroke-width:1px;}#chatgpt-mermaid-\_r_ng\_ .cluster text{fill:rgb(13, 13, 13);}#chatgpt-mermaid-\_r_ng\_ .cluster span{color:rgb(13, 13, 13);}#chatgpt-mermaid-\_r_ng\_ div.mermaidTooltip{position:absolute;text-align:center;max-width:200px;padding:2px;font-family:-apple-system-body,ui-sans-serif,-apple-system,system-ui,"Segoe UI",Helvetica,"Apple Color Emoji",Arial,sans-serif,"Segoe UI Emoji","Segoe UI Symbol";font-size:12px;background:rgb(243, 243, 243);border:1px solid rgba(0, 0, 0, 0.1);border-radius:2px;pointer-events:none;z-index:100;}#chatgpt-mermaid-\_r_ng\_ .flowchartTitleText{text-anchor:middle;font-size:18px;fill:rgb(13, 13, 13);}#chatgpt-mermaid-\_r_ng\_ rect.text{fill:none;stroke-width:0;}#chatgpt-mermaid-\_r_ng\_ .icon-shape,#chatgpt-mermaid-\_r_ng\_ .image-shape{background-color:rgb(252, 252, 252);text-align:center;}#chatgpt-mermaid-\_r_ng\_ .icon-shape p,#chatgpt-mermaid-\_r_ng\_ .image-shape p{background-color:rgb(252, 252, 252);padding:2px;}#chatgpt-mermaid-\_r_ng\_ .icon-shape .label rect,#chatgpt-mermaid-\_r_ng\_ .image-shape .label rect{opacity:0.5;background-color:rgb(252, 252, 252);fill:rgb(252, 252, 252);}#chatgpt-mermaid-\_r_ng\_ .label-icon{display:inline-block;height:1em;overflow:visible;vertical-align:-0.125em;}#chatgpt-mermaid-\_r_ng\_ .node .label-icon path{fill:currentColor;stroke:revert;stroke-width:revert;}#chatgpt-mermaid-\_r_ng\_ .node .neo-node{stroke:rgb(83, 154, 248);}#chatgpt-mermaid-\_r_ng\_ [data-look="neo"].node rect,#chatgpt-mermaid-\_r_ng\_ [data-look="neo"].cluster rect,#chatgpt-mermaid-\_r_ng\_ [data-look="neo"].node polygon{stroke:url(#chatgpt-mermaid-\_r_ng\_-gradient);filter:drop-shadow( 1px 2px 2px rgba(185,185,185,1));}#chatgpt-mermaid-\_r_ng\_ [data-look="neo"].swimlane.cluster rect{filter:none;}#chatgpt-mermaid-\_r_ng\_ [data-look="neo"].node path{stroke:url(#chatgpt-mermaid-\_r_ng\_-gradient);stroke-width:1px;}#chatgpt-mermaid-\_r_ng\_ [data-look="neo"].node .outer-path{filter:drop-shadow( 1px 2px 2px rgba(185,185,185,1));}#chatgpt-mermaid-\_r_ng\_ [data-look="neo"].node .neo-line path{stroke:rgb(83, 154, 248);filter:none;}#chatgpt-mermaid-\_r_ng\_ [data-look="neo"].node circle{stroke:url(#chatgpt-mermaid-\_r_ng\_-gradient);filter:drop-shadow( 1px 2px 2px rgba(185,185,185,1));}#chatgpt-mermaid-\_r_ng\_ [data-look="neo"].node circle .state-start{fill:#000000;}#chatgpt-mermaid-\_r_ng\_ [data-look="neo"].icon-shape .icon{fill:url(#chatgpt-mermaid-\_r_ng\_-gradient);filter:drop-shadow( 1px 2px 2px rgba(185,185,185,1));}#chatgpt-mermaid-\_r_ng\_ [data-look="neo"].icon-shape .icon-neo path{stroke:url(#chatgpt-mermaid-\_r_ng\_-gradient);filter:drop-shadow( 1px 2px 2px rgba(185,185,185,1));}#chatgpt-mermaid-\_r_ng\_ .node text{font-size:14px;font-weight:600;letter-spacing:normal;fill:rgb(0, 79, 153);}#chatgpt-mermaid-\_r_ng\_ .edgeLabels text{font-size:13px;font-weight:600;letter-spacing:-0.08px;fill:rgb(0, 79, 153);}#chatgpt-mermaid-\_r_ng\_ .node tspan[font-weight="normal"],#chatgpt-mermaid-\_r_ng\_ .edgeLabels tspan[font-weight="normal"]{font-weight:600;}#chatgpt-mermaid-\_r_ng\_ .edgeLabel .label rect{opacity:1;rx:13px;ry:13px;fill:rgb(245, 250, 255);stroke:rgb(206, 219, 229);stroke-width:1px;}#chatgpt-mermaid-\_r_ng\_ .node rect,#chatgpt-mermaid-\_r_ng\_ .node circle,#chatgpt-mermaid-\_r_ng\_ .node ellipse,#chatgpt-mermaid-\_r_ng\_ .node polygon,#chatgpt-mermaid-\_r_ng\_ .node path{fill:rgb(229, 243, 255);stroke:rgba(0, 0, 0, 0.1);stroke-width:1px;}#chatgpt-mermaid-\_r_ng\_ .node rect{rx:16px;ry:16px;}#chatgpt-mermaid-\_r_ng\_ .mermaid-decision .label-container{fill:rgb(245, 250, 255);stroke:rgb(206, 219, 229);stroke-dasharray:2,2;}#chatgpt-mermaid-\_r_ng\_ .edgePaths .flowchart-link{stroke:rgb(143, 143, 143);stroke-width:1px;stroke-linecap:round;stroke-linejoin:round;}#chatgpt-mermaid-\_r_ng\_ .marker{fill:rgb(143, 143, 143);stroke:rgb(143, 143, 143);}#chatgpt-mermaid-\_r_ng\_ :root{--mermaid-font-family:-apple-system-body,ui-sans-serif,-apple-system,system-ui,"Segoe UI",Helvetica,"Apple Color Emoji",Arial,sans-serif,"Segoe UI Emoji","Segoe UI Symbol";}Customer checkoutValidate cartRecalculate pricesValidate couponCalculate deliveryCreate checkout quoteCustomer confirmsStart database transactionReserve inventoryCreate order and snapshotsCreate payment recordCommit transactionPayment methodConfirm COD orderInitiate gatewayVerify paymentPayment successful?Mark paidKeep pending or expireFulfilmentRelease reservation on expiryCODOnlineYesNo

### My recommended checkout implementation

Use two separate stages:

Stage 1: Checkout quote

- Validate variants.
- Validate available stock.
- Calculate prices.
- Apply coupons.
- Calculate shipping.
- Return a quote with expiry.

Stage 2: Order placement

- Revalidate the quote and prices.
- Begin database transaction.
- Lock affected inventory rows.
- Check available stock again.
- Reserve inventory.
- Create order and order items.
- Create payment transaction.
- Save outbox event.
- Commit transaction.

The quote is not a guarantee of inventory availability. Inventory must be rechecked at order placement.

### Payment and inventory reservation rules

For online payments:

- Reserve stock for a configurable period, for example 15 minutes.
- Initiate the gateway transaction after committing the order.
- Do not hold a database transaction open while waiting for the gateway.
- Verify the gateway response server-side.
- Process callbacks idempotently.
- Release stock when the reservation expires, unless payment is confirmed.
- Handle late successful payments with a reconciliation policy.

For COD:

- Create the order as pending confirmation.
- Reserve stock.
- Confirm the order through the appropriate business process.
- Release stock when cancelled or reservation expiry is reached.

Do not blindly assume that a failed browser redirect means payment failed. Payment state must come from verified gateway information.

## 7.3 Outbox and event architecture

Introduce an Outbox table from the beginning, even if you don't deploy Kafka yet.

Example:

```
outbox_events
├── id
├── aggregate_type
├── aggregate_id
├── event_type
├── payload
├── occurred_at
├── published_at
├── attempts
├── last_error
└── created_at
```

Events to define:

| Event             | Trigger                     |
| ----------------- | --------------------------- |
| ProductPublished  | Product becomes visible     |
| ProductUpdated    | Product changes             |
| InventoryAdjusted | Stock adjustment            |
| InventoryReserved | Checkout reserves stock     |
| OrderPlaced       | Order created               |
| PaymentConfirmed  | Payment verified            |
| OrderCancelled    | Order cancelled             |
| ShipmentCreated   | Courier consignment created |
| OrderDelivered    | Delivery confirmed          |

Initially, Laravel listeners and queued jobs can process these events. Later, the outbox publisher can publish the same event contracts to Kafka.

One caution: Outbox publishing is normally at-least-once, not exactly-once. Every consumer must be idempotent.

# 8. Frontend architecture and UI structure

I recommend building a clean fashion-commerce experience rather than a generic ecommerce template.

## 8.1 Storefront pages

[COS - Interaction Design :: Behance](https://images.openai.com/static-rsc-4/k6u9l58i2ycl3ICLiJmLiy3ai-hjcD2-WQ2chyZG-twqEnEq3LwpN7JztfnPbTFxrLbAzsPbdWjAgaE86Ayiqa6K7F1L6A4hADK0M9hHiOtkJAaohHvtAIgivNv1hKcgIhO9GAtCh3PcQ4T6-CKMdOgnlZ0Mnl9qWC2N1Tj2wjw?purpose=inline)

Homepage

Hero banners, new arrivals, featured collections, trending products, promotional sections.

[Modern e-commerce site by Halyna Yavurek on Dribbble](https://images.openai.com/static-rsc-4/nb__I4hCbWoS222QZZAHTD4UJeixIw3fFn_JF8tiX2EM1zuGVfFIvYf8Ifb1Aqy-T8RJiRO-ms3-3I06hsdU8PSFEk6GeLjXWleG7eFYBbtYjv1g6ISMsPQ7kqSdTFJfs16t20IEB0xPBftbdzxyBRyLst_YxW1klmsmRcBssII?purpose=inline)

Product listing

Category filters, size, color, price range, sorting, pagination.

[Clothing Brand-Product Page UI by Akshay Anil on Dribbble](https://images.openai.com/static-rsc-4/F_79GwpgGaG_3B6UM5_4ByN3FidGKKipplC3vSH3wTJumXB_cJKwSYaFdfDeMIQVn4dR3pVRa9XaG2YBc7ExjHXYVRigvP4H_2jWknT3ILrbzDVkvlVluNAUOzbhXcm0HOmMxcVXPUJIWF75TNAdvXPr-xPOqoOYy0y23_MsCj0?purpose=inline)

Product details

Gallery, variant selector, size guide, availability, price, related products.

[Seamless E-Commerce Checkout Page — UI/UX Design by Maria Akter on Dribbble](https://images.openai.com/static-rsc-4/BwCy3jnWluAxC5Thv9uqIw47RPW4oE7gnYIwvacvUiNDrdm1oDIr3r2Em28FI-cVIzdjzmNW4lRNzO6uCG6PlN9gRcBVGQwraicN_bjlUbTgsh8tUfsVR2pSVftzF05C5Ni0pl-7Z4A8ZDZBEffxEZRXFxTTl3ZALOr462H1138?purpose=inline)

Checkout

Address, delivery method, coupon, payment selection, order summary.

[Ecomora Admin Dashboard - E-commerce Analytics UI by Md Foysal ✪ for ZERO STUDIO on Dribbble](https://images.openai.com/static-rsc-4/XYl05bFib2zlXMqrPtPGQnpmr7crzhpkvQ0BG74r_A4KyHOv6trizgDrBeQKd6eL4yGXncZ3wS4AZ5xKpQz9fGW1kQT9riZDBbfQga4DMKEH-RYQnwIZgjjaA2Ftb3KLySNWtNu4o7uQffolH7A6xg-lZ9v5qNEIBnzOSXyEEjY?purpose=inline)

Admin dashboard

Sales overview, order queue, products, stock alerts, customers, reports.

## 8.2 Frontend state management

I recommend separating server data from local interaction state.

| Requirement               | Technology                        |
| ------------------------- | --------------------------------- |
| API server data           | TanStack Query or server fetching |
| Shopping cart interaction | Zustand                           |
| Form management           | React Hook Form                   |
| Validation                | Zod                               |
| UI components             | shadcn/ui                         |
| URL filters               | URL search parameters             |
| Authentication            | Sanctum cookie session            |

Don't maintain duplicate product and order databases in Zustand. It should manage temporary UI state, not replace Laravel as the source of truth.

## 8.3 Next.js rendering strategy

| Page             | Strategy                              |
| ---------------- | ------------------------------------- |
| Homepage         | Server rendering + controlled caching |
| Category pages   | Server rendering + cache              |
| Product pages    | Server rendering + cache              |
| Search           | Dynamic                               |
| Cart             | Private/dynamic                       |
| Checkout         | Private/dynamic                       |
| Customer account | Private/dynamic                       |
| Admin            | Private/dynamic                       |

Use canonical URLs, metadata, Open Graph images, structured Product data and XML sitemaps.

For the catalog, define cache invalidation when products, categories, prices and availability change. Never publicly cache private account or checkout data.

# 9. Infrastructure and deployment architecture

For your first release, use Docker Compose rather than Kubernetes.

## 9.1 Local development environment

```
Developer Machine
       |
       v
Docker Compose
       |
       +---- web
       |      Next.js 16 :3000
       |
       +---- api
       |      Laravel PHP-FPM
       |
       +---- nginx
       |      Reverse Proxy :8080
       |
       +---- mysql
       |      MySQL :3306
       |
       +---- redis
       |      Redis :6379
       |
       +---- queue
       |      Laravel queue worker
       |
       +---- scheduler
              Laravel scheduler
```

Suggested containers:

| Container            | Purpose         |
| -------------------- | --------------- |
| `commerce-web`       | Next.js         |
| `commerce-api`       | Laravel PHP-FPM |
| `commerce-nginx`     | Reverse proxy   |
| `commerce-mysql`     | Database        |
| `commerce-redis`     | Cache and queue |
| `commerce-worker`    | Queue worker    |
| `commerce-scheduler` | Scheduled tasks |

For local development, you may use Next.js's development server and Laravel's development server instead of containerizing everything immediately. Keep the production architecture container-ready.

## 9.2 Production deployment

Suggested domains:

```
www.meherfashion.com.com       -> Next.js storefront
admin.meherfashion.com.com     -> Optional separate admin hostname
api.meherfashion.com.com       -> Laravel API
```

Even if the admin is under the same Next.js deployment, you can later map it to a separate hostname using routing and DNS.

Production infrastructure:

```
Internet
   |
   v
Cloudflare / CDN
   |
   v
NGINX / Reverse Proxy
   |
   +------> Next.js
   |
   +------> Laravel API
                |
                +----> MySQL
                |
                +----> Redis
                |
                +----> Queue workers
                |
                +----> Object storage
                |
                +----> Payment / Courier APIs
```

Use separate production, staging and local environments.

## 9.3 CI/CD workflow

For your monorepo, implement path-based deployment:

| Changed paths       | Pipeline                   |
| ------------------- | -------------------------- |
| `apps/web/**`       | Frontend build/test/deploy |
| `apps/api/**`       | Backend test/deploy        |
| `packages/**`       | Relevant frontend checks   |
| `infrastructure/**` | Infrastructure validation  |
| `docs/**`           | Documentation checks       |

Important deployment steps:

Backend

1. Checkout code.
2. Install Composer dependencies.
3. Run static analysis and tests.
4. Build release artifact.
5. Deploy to server.
6. Run database migrations.
7. Clear/rebuild Laravel caches.
8. Restart queue workers gracefully.
9. Verify health endpoint.

Frontend

1. Checkout code.
2. Install dependencies with frozen lockfile.
3. Run lint and type checking.
4. Build Next.js.
5. Run tests.
6. Deploy release.
7. Restart application if required.
8. Verify storefront and admin health.

Never deploy frontend or backend directly from an unreviewed development branch.

## 9.4 Production observability

Start with:

- Laravel structured logs.
- Queue failure monitoring.
- Database slow query logs.
- Uptime monitoring.
- Payment callback failure alerts.
- Inventory reconciliation.
- Daily database backups.
- Object-storage backups/versioning.

Later add:

- Prometheus.
- Grafana.
- Loki.
- OpenTelemetry.
- Sentry.

For an e-commerce application, payment failures, stuck orders, inventory inconsistencies and failed courier synchronization should be monitored separately from generic server errors.

# 10. Initial implementation roadmap

I recommend a 12-phase delivery plan. Each phase should produce a working, testable increment.

## Implementation roadmap

Suggested sequence; estimates assume one experienced developer using AI assistance.

## 01

Repository foundation

Monorepo, Docker, Next.js, Laravel, CI, documentation

2–3 days

## 02

Identity and authorization

Authentication, Sanctum, RBAC, customer/admin roles

3–4 days

## 03

Catalog management

Categories, products, images, sizes, colors, variants

5–7 days

## 04

Inventory management

Warehouses, stock, movements, reservations

3–5 days

## 05

Storefront

Homepage, catalog, search, product details, SEO

5–7 days

## 06

Cart and checkout

Cart, pricing, addresses, shipping quote, checkout

4–6 days

## 07

Orders and payments

Order lifecycle, COD, payment gateway integration

5–7 days

## 08

Admin operations

Order management, fulfilment, customers, reports

4–6 days

## 09

Courier integration

Consignment, tracking, COD reconciliation

3–5 days

## 10

Promotions and reviews

Coupons, campaigns, reviews, wishlist

3–4 days

## 11

Quality and security

E2E, performance, penetration testing, reconciliation

5–7 days

## 12

Production launch

CI/CD, backups, monitoring, deployment, smoke tests

3–5 days

Estimated total: 45–66 working days

Planning estimate, not a delivery guarantee. Payment-provider onboarding, courier credentials, design iterations and production feedback can increase the timeline.

## Phase 1 — Repository foundation

Deliverables:

- Git repository.
- Monorepo initialization.
- Next.js 16 setup.
- Laravel 13 setup.
- Docker Compose.
- MySQL and Redis.
- NGINX.
- Environment configuration.
- GitHub Actions.
- `AGENTS.md`.
- Architecture documentation.

Acceptance criteria:

- `web` runs successfully.
- `api` returns health response.
- MySQL connection works.
- Redis connection works.
- Queue worker runs.
- Frontend can communicate with backend.
- CI pipeline passes.

## Phase 2 — Identity and authorization

Deliverables:

- Customer registration.
- Customer login/logout.
- Admin login/logout.
- Password reset.
- Email verification.
- Sanctum authentication.
- Spatie RBAC.
- Role/permission seeding.
- Admin route authorization.
- Audit log foundation.

Acceptance criteria:

- Guest cannot access protected customer routes.
- Customer cannot access admin APIs.
- Admin can only access permitted modules.
- Login/logout and session expiration work.
- CSRF and cookie security are verified.

## Phase 3 — Catalog management

Deliverables:

- Category CRUD.
- Product CRUD.
- Brand management.
- Product images.
- Variant creation.
- Size and color.
- SKU generation.
- Product publishing.
- Product SEO fields.
- Bulk import foundation.

Acceptance criteria:

- Products can have multiple variants.
- SKU uniqueness is enforced.
- Image uploads are validated.
- Draft products are not publicly accessible.
- Product listings support pagination and filtering.

## Phase 4 — Inventory management

Deliverables:

- Warehouse foundation.
- Variant stock.
- Stock adjustments.
- Stock movement history.
- Low-stock alerts.
- Inventory reservations.
- Reservation expiry jobs.

Acceptance criteria:

- Stock cannot go negative.
- Every adjustment creates a movement record.
- Concurrent reservation tests pass.
- Expired reservations release stock safely.

## Phase 5 — Storefront

Deliverables:

- Homepage.
- Category pages.
- Product listings.
- Product detail pages.
- Color/size selection.
- Search.
- Filters and sorting.
- Responsive mobile UI.
- SEO metadata.
- Sitemap.
- Product structured data.

Acceptance criteria:

- Products load from the real Laravel API.
- Only published products appear.
- Product pages have canonical URLs.
- Mobile layouts work.
- Catalog cache invalidation works.

## Phase 6 — Cart and checkout

Deliverables:

- Guest cart.
- Authenticated cart.
- Cart merging.
- Quantity management.
- Price calculation.
- Coupon validation.
- Customer address management.
- Delivery charge calculation.
- Checkout quote.
- Order placement.

Acceptance criteria:

- Backend recalculates all prices.
- Stock is revalidated.
- Duplicate order requests are prevented.
- Checkout is transaction-safe.
- Order snapshots are saved correctly.

## Phase 7 — Orders and payments

Deliverables:

- Order number generation.
- Order status history.
- COD.
- First online gateway.
- Payment transaction records.
- Gateway verification.
- Webhook handling.
- Payment reconciliation.
- Cancellation and refunds foundation.

Acceptance criteria:

- Duplicate payment callbacks cannot duplicate payment.
- Failed payments are not marked successful.
- Order totals match transaction amounts.
- Gateway secrets remain server-side.
- Payment and order statuses are independently managed.

## Phase 8 — Admin operations

Deliverables:

- Sales dashboard.
- Order list and details.
- Order confirmation.
- Order cancellation.
- Fulfilment workflow.
- Customer management.
- Stock overview.
- Reports.

Acceptance criteria:

- Dashboard metrics match database records.
- Admin activity is audited.
- Status transitions are validated.
- Access is controlled by permissions.

## Phase 9 — Courier integration

Deliverables:

- Courier interface.
- First courier adapter.
- Consignment creation.
- Tracking synchronization.
- Delivery updates.
- Failed delivery handling.
- COD reconciliation.

## Phase 10 — Promotions and customer experience

Deliverables:

- Coupons.
- Fixed and percentage discounts.
- Minimum order value.
- Usage limits.
- Campaigns.
- Wishlist.
- Product reviews.
- Related products.

## Phase 11 — Quality and security

Deliverables:

- Unit tests.
- Integration tests.
- Playwright purchase journey.
- Load tests.
- Security review.
- SQL/index review.
- Backup restoration test.
- Inventory reconciliation tests.
- Payment reconciliation tests.

## Phase 12 — Production launch

Deliverables:

- Production environment.
- HTTPS.
- DNS and CDN.
- Database backups.
- CI/CD deployment.
- Queue workers.
- Scheduled tasks.
- Monitoring.
- Error alerts.
- Smoke tests.
- Rollback strategy.

# 11. Codex implementation prompt library

This is the part I would use most heavily during development.

I recommend having one master instruction file and a sequence of phase-specific prompts.

Rule: Codex should implement one phase at a time. Don't let it generate the entire application in one run.

## Prompt 0 — Architecture review and planning

Run first

```

You are acting as a Principal Software Architect and
Senior Full-Stack Engineer.

I am building a single-brand clothing e-commerce
platform for Bangladesh.

Technology:
- Next.js 16
- TypeScript
- Laravel 13
- PHP 8.3+
- MySQL 8
- Redis
- Laravel Sanctum
- Spatie Permission
- Docker Compose
- REST API

Architecture:
- Monorepo
- Modular Monolith
- One storefront
- One admin dashboard
- One backend API
- Single MySQL database
- Future microservices extraction readiness

Your first task is NOT implementation.

Inspect the existing repository.

Create or review:

1. docs/architecture/system-architecture.md
2. docs/architecture/backend-architecture.md
3. docs/architecture/frontend-architecture.md
4. docs/database/database-design.md
5. docs/api/api-conventions.md
6. docs/security/security-architecture.md
7. docs/workflows/checkout-workflow.md
8. docs/adr/

Identify:
- Missing architectural decisions
- Incorrect abstractions
- Domain boundaries
- Database ownership
- Security risks
- Integration dependencies
- Future scaling limitations

Create a phased implementation plan.

Do not generate unnecessary code.
Do not invent business requirements.
Record unresolved decisions explicitly.

Wait for architecture approval before implementation.

```

## Prompt 1 — Initialize monorepo

```

Implement Phase 1: Monorepo Foundation.

Repository structure:

apps/web  = Next.js 16
apps/api  = Laravel 13
packages  = Shared TypeScript packages
docs      = Architecture documentation
infrastructure = Docker and deployment

Tasks:

1. Initialize pnpm workspace.
2. Initialize Next.js 16 with strict TypeScript.
3. Initialize Laravel 13 using PHP 8.3+.
4. Configure Docker Compose.
5. Configure MySQL 8.
6. Configure Redis.
7. Configure NGINX.
8. Configure Laravel queue worker.
9. Configure Laravel scheduler.
10. Configure environment variables.
11. Add health endpoints.
12. Add GitHub Actions CI.
13. Create root README.
14. Create AGENTS.md.

Requirements:

- Never commit .env files.
- Use separate development and production configs.
- Pin dependencies through lockfiles.
- Avoid unnecessary dependencies.
- Use health checks and persistent database volumes.
- Keep secrets out of Docker images.
- Use non-root container users where practical.

Validation:

- Docker containers start.
- Next.js loads.
- Laravel responds.
- MySQL connection passes.
- Redis connection passes.
- Queue worker processes a test job.
- CI succeeds.

Provide a report of all generated files,
commands and verification results.

```

## Prompt 2 — Identity and RBAC

```

Implement the Identity module.

Business model:
Single-brand clothing store in Bangladesh.

Requirements:

Customer:
- Registration
- Login
- Logout
- Password reset
- Email verification
- Profile management
- Address management

Admin:
- Admin login
- Role management
- Permission management
- User management
- Audit logging

Use:
- Laravel Sanctum
- Spatie Laravel Permission
- Form Requests
- API Resources
- Policies
- Feature tests

Roles:
- super_admin
- product_manager
- inventory_manager
- order_manager
- customer_support
- marketing_manager
- report_viewer

Requirements:

1. Secure session authentication.
2. Correct CSRF configuration.
3. Secure cookie settings.
4. Rate limiting.
5. Password hashing.
6. Permission-based API authorization.
7. Prevent privilege escalation.
8. Never expose sensitive user information.
9. Audit important admin actions.

Do not use localStorage for session credentials.

Create migrations, seeders, services, controllers,
requests, resources, policies and tests.

Verify customer/admin access boundaries.

```

## Prompt 3 — Catalog module

```

Implement the Catalog module.

Entities:

- Brand
- Category
- Product
- ProductImage
- ProductVariant
- Attribute
- AttributeValue
- VariantAttributeValue

Requirements:

Product:
- Name
- Slug
- Description
- Brand
- Category
- SEO metadata
- Publication status
- Featured flag

Variants:
- Unique SKU
- Color
- Size
- Selling price
- Compare-at price
- Cost price
- Status

Features:

1. Category CRUD.
2. Product CRUD.
3. Variant management.
4. Multiple product images.
5. Image ordering.
6. Product publishing.
7. Product filtering.
8. Product pagination.
9. Search.
10. Bulk product import foundation.

Architecture:

- Keep catalog business rules in Catalog.
- Keep stock management in Inventory.
- Never expose unpublished products publicly.
- Use API Resources.
- Use Form Requests.
- Add appropriate indexes.
- Use database transactions where necessary.
- Add automated tests.

Product image uploads must use storage
abstraction, not hardcoded local paths.

Implement only this module.
Do not modify unrelated modules.

```

## Prompt 4 — Inventory module

```

Implement the Inventory module.

Entities:

- Warehouse
- InventoryItem
- InventoryMovement
- StockReservation
- StockReservationItem

Requirements:

1. Variant-based inventory.
2. Multiple warehouse support.
3. Stock adjustments.
4. Stock movement history.
5. Reservation management.
6. Reservation expiry.
7. Low-stock notifications.
8. Inventory reporting.

Rules:

Available stock =
quantity_on_hand - quantity_reserved

Never allow negative stock.

Every stock modification must create
an inventory movement record.

Use:
- Database transactions
- SELECT FOR UPDATE
- Appropriate indexes
- Idempotent reservation operations
- Scheduled reservation expiry

Expose public application interfaces
for other modules.

Do not allow Catalog or Orders
to directly update inventory tables.

Write concurrency and integration tests.

```

## Prompt 5 — Storefront

```

Implement the customer storefront using Next.js 16.

Technology:
- TypeScript strict mode
- App Router
- Server Components
- Tailwind CSS
- shadcn/ui
- TanStack Query where appropriate

Pages:

- Homepage
- Category listing
- Product listing
- Product details
- Search
- Cart
- Checkout
- Customer account
- Order history

Requirements:

1. Responsive mobile-first design.
2. Fashion-oriented visual experience.
3. Product image gallery.
4. Color and size selection.
5. Filters and sorting.
6. SEO metadata.
7. Canonical URLs.
8. Product structured data.
9. Accessible UI.
10. API integration with Laravel.

Rules:

- Server Components by default.
- Client Components only when required.
- No hardcoded production products.
- Do not calculate authoritative prices on frontend.
- Do not expose private backend credentials.
- Do not publicly cache customer data.
- Implement cache invalidation.

Use realistic loading, empty and error states.
Write component and integration tests.

```

## Prompt 6 — Cart and checkout

```

Implement Cart and Checkout modules.

Features:

- Guest cart
- Customer cart
- Cart merging
- Add/update/remove items
- Address selection
- Coupon validation
- Shipping calculation
- Checkout quote
- Order placement

Bangladesh requirements:

- BDT currency
- Bangladesh phone validation
- Division/district/upazila address fields
- COD support
- Online payment preparation

Critical rules:

1. Backend owns all pricing calculations.
2. Revalidate product variants.
3. Revalidate stock during order placement.
4. Use idempotency keys.
5. Use database transactions.
6. Lock inventory rows.
7. Save order snapshots.
8. Never trust frontend totals.
9. Prevent duplicate orders.
10. Never hold DB locks during external API calls.

Create comprehensive tests for:
- Concurrent checkout
- Out-of-stock variants
- Coupon expiry
- Invalid quantities
- Duplicate requests
- Price changes
- Expired quotes

Do not implement a fake payment gateway.

```

## Prompt 7 — Orders and payments

```

Implement Orders and Payments modules.

Order functionality:

- Order creation
- Unique order number
- Order item snapshots
- Order status history
- Order cancellation
- Customer order history
- Admin order management

Payment functionality:

- COD
- PaymentGateway interface
- Gateway adapters
- Transaction records
- Payment initiation
- Server-side verification
- Webhook processing
- Refund foundation

Design adapters for:
- bKash
- Nagad
- SSLCommerz

Do not assume provider API behavior.
Follow official merchant documentation.

Critical requirements:

1. Payment callbacks must be idempotent.
2. Verify payment server-side.
3. Match amount and currency.
4. Validate merchant and transaction references.
5. Prevent duplicate payment records.
6. Never expose gateway credentials.
7. Separate payment status from order status.
8. Handle delayed and repeated callbacks.
9. Record all gateway requests and responses
   with sensitive data redacted.

Build a mock gateway for automated testing.
Do not use mock payment results in production.

```

## Prompt 8 — Admin dashboard

```

Implement the centralized Admin Dashboard.

Modules:

- Dashboard
- Products
- Categories
- Variants
- Inventory
- Orders
- Payments
- Customers
- Promotions
- Shipping
- Reviews
- Reports
- Settings
- User and permission management

Requirements:

- Responsive admin layout.
- Sidebar navigation.
- Permission-aware menu.
- Server-side authorization.
- Searchable data tables.
- Pagination.
- Filters.
- Export capability.
- Confirmation dialogs.
- Audit history.

Dashboard metrics:
- Total sales
- Orders today
- Pending orders
- Delivered orders
- Revenue
- Average order value
- Low-stock products
- Best-selling products

All metrics must come from backend APIs.

Do not hardcode analytics data.
Protect every admin endpoint with permissions.

```

## Prompt 9 — Courier integration

```

Implement the Shipping module.

Define a CourierProvider interface.

Operations:

- Calculate delivery charge
- Create shipment
- Cancel shipment
- Track shipment
- Process tracking webhook

Prepare adapters for:

- Pathao
- Steadfast
- REDX

Implement one provider first using official
merchant API documentation and credentials.

Requirements:

1. Store consignment references.
2. Store tracking events.
3. Map external statuses to internal statuses.
4. Handle retries.
5. Prevent duplicate shipment creation.
6. Log API errors safely.
7. Support COD collection reconciliation.
8. Handle returned parcels.

Do not make courier-specific assumptions
inside the Orders module.

```

## Prompt 10 — Production readiness

```

Perform a production-readiness audit.

Inspect the entire repository.

Audit:

1. Authentication
2. Authorization
3. CSRF and CORS
4. SQL injection
5. XSS
6. Rate limiting
7. Payment verification
8. Webhook security
9. Inventory concurrency
10. Checkout idempotency
11. Database indexes
12. Slow queries
13. Cache invalidation
14. Queue failures
15. Logging
16. Error handling
17. Image optimization
18. SEO
19. Accessibility
20. Deployment security

Run:

- Backend tests
- Frontend tests
- Static analysis
- Type checking
- Playwright E2E
- Load tests
- Dependency security checks

Test complete purchase journey:

Product selection
-> Cart
-> Checkout
-> Order
-> Payment
-> Fulfilment
-> Delivery

Identify all critical and high-severity issues.

Do not claim production readiness until
the tests have passed and all critical
issues have been addressed.

Produce a final audit report.

```

# 12. Engineering guardrails for vibe coding

AI coding agents can generate code quickly, but the biggest risk is architectural inconsistency across multiple implementation sessions.

I would enforce these rules.

## 12.1 Repository-level instructions

Maintain these files:

| File                  | Purpose                              |
| --------------------- | ------------------------------------ |
| `/AGENTS.md`          | Global coding and architecture rules |
| `/apps/web/AGENTS.md` | Next.js-specific instructions        |
| `/apps/api/AGENTS.md` | Laravel-specific instructions        |
| `/docs/architecture/` | Architecture decisions               |
| `/docs/database/`     | Database contracts                   |
| `/docs/api/`          | API specifications                   |

Every time Codex starts a new task, it should inspect the relevant instructions and existing implementation.

## 12.2 Definition of Done

A feature is not complete simply because the UI works.

Use this checklist:

Feature completion checklist

0/12 completed

Business requirements implemented

Database migrations reviewed

Authorization and validation implemented

API contract documented

Frontend integration completed

Unit tests passed

Integration tests passed

Error and edge cases handled

Security implications reviewed

Documentation updated

No unrelated changes introduced

Git changes reviewed

Reset checklist

## 12.3 Git branching strategy

Keep the workflow simple:

```
main
  |
  +--- develop
         |
         +--- feature/identity
         +--- feature/catalog
         +--- feature/inventory
         +--- feature/cart-checkout
         +--- feature/payments
         +--- feature/shipping
```

Recommended process:

1. Create a feature branch.
2. Ask Codex to inspect and plan.
3. Implement the feature.
4. Run tests.
5. Review the diff.
6. Merge into `develop`.
7. Deploy to staging.
8. Merge into `main` after acceptance.

For a solo developer, you can simplify this to feature branches and pull requests directly into `main` if you have automated staging and deployment checks.

# 13. Architecture decisions I would lock before development

These are the decisions I consider particularly important for your project.

| Decision              | Recommendation            |
| --------------------- | ------------------------- |
| Frontend applications | One Next.js application   |
| Backend applications  | One Laravel application   |
| Laravel architecture  | Modular Monolith          |
| Domain structure      | Domain-oriented modules   |
| Product variants      | Independent SKU           |
| Inventory             | Separate Inventory module |
| Money                 | BDT, decimal-safe         |
| Authentication        | Sanctum                   |
| Admin authorization   | Spatie Permission         |
| Checkout              | Transactional workflow    |
| Payment integration   | Gateway adapters          |
| Shipping integration  | Courier adapters          |
| Events                | Transactional Outbox      |
| API documentation     | OpenAPI                   |
| Search                | MySQL initially           |
| Advanced search       | Typesense later           |
| Message broker        | Kafka later               |
| Deployment            | Docker Compose initially  |
| Testing               | Pest + Playwright         |

### Five things I would specifically avoid

1. Don't build a generic multi-vendor marketplace architecture.
2. Don't create separate microservices for each business module at the beginning.
3. Don't put inventory calculations inside the Product model.
4. Don't let the frontend calculate order totals or authorize users.
5. Don't allow AI agents to modify the database architecture without reviewing the impact on existing modules.

## 14. Final architecture reference

# Clothing Commerce Platform

Target architecture · Initial production release

Frontend

- Next.js 16
- TypeScript
- Tailwind CSS
- shadcn/ui
- TanStack Query

  Backend
- Laravel 13
- Modular Monolith
- Sanctum
- Spatie Permission
- Domain Events
- Transactional Outbox

  Infrastructure
- MySQL 8
- Redis
- Docker
- NGINX
- GitHub Actions

  Business capabilities
- Catalog and Variants
- Inventory and Stock
- Cart and Checkout
- Orders and Payments
- Customers and Promotions
- Shipping and Notifications

  Future evolution
- Kafka
- Typesense
- Independent services
- Prometheus / Grafana
- Horizontal scaling

Official technical references

- [Laravel 13 release and support policy](https://laravel.com/framework/docs/releases?utm_source=chatgpt.com) — Laravel 13 requires PHP 8.3+ and receives security support until March 17, 2028.
- [Laravel application directory structure](https://laravel.com/framework/docs/structure?utm_source=chatgpt.com).
- [Laravel installation and API backend documentation](https://laravel.com/framework/docs?utm_source=chatgpt.com).
- [Next.js documentation](https://nextjs.org/docs?utm_source=chatgpt.com).
- [Laravel Sanctum documentation](https://laravel.com/docs/13.x/sanctum?utm_source=chatgpt.com).

My architectural conclusion: Build the first version as a disciplined modular monolith. Make product variants, inventory consistency, checkout reliability, payment verification and domain ownership your core engineering priorities. These foundations will allow you to grow from a single clothing store into a much larger commerce platform without having to rewrite the entire system.