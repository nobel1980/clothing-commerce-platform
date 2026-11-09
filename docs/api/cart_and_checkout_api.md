# Cart & Checkout API Contract

## 1. Overview & Architectural Invariants

The Cart and Checkout modules enforce strict server-side pricing, stock reservation, and Bangladesh localization rules:

1. **Authoritative Backend Pricing (`BDT`)**: All monetary amounts are stored and calculated in integer subunits (`1 BDT = 100 paisa`) via `App\Shared\Money\Money`. Client-supplied prices, subtotals, discounts, or totals are never trusted.
2. **Bangladesh Localization**:
   - Currency: `BDT` (`৳`).
   - Mobile Phone Validation: `^(?:\+?8801|01)[3-9]\d{8}$` (normalized to 11-digit `01XXXXXXXXX`).
   - Address Fields: `division` (`Dhaka`, `Chattogram`, `Rajshahi`, `Khulna`, `Barishal`, `Sylhet`, `Rangpur`, `Mymensingh`), `district`, `upazila_thana`, `street_address`, and 4-digit `postal_code`.
3. **Variant & Stock Revalidation**: Every cart mutation, quote calculation, and order placement revalidates that each `ProductVariant` is `active`, its parent `Product` is `published`, and sufficient stock (`quantity_on_hand - quantity_reserved`) is available via `InventoryServiceContract`.
4. **Idempotent Order Placement & Row Locking**: `POST /api/checkout/orders` requires/supports an `Idempotency-Key` header or `idempotency_key` body field, acquires `SELECT ... FOR UPDATE` row locks in deterministic ID order inside `DB::transaction`, saves immutable `Order` and `OrderItem` snapshots, and executes post-commit hooks outside the transaction so database locks are never held during external API calls.

---

## 2. Cart Endpoints (`/api/cart`)

Guest carts are identified via the `X-Guest-Token` header or `guest_token` body/query parameter. Authenticated customer carts use Bearer / Sanctum session authentication.

| Method | Endpoint | Description | Auth |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/cart` | Retrieve active customer or guest cart with revalidated prices and stock | Optional (`sanctum` or `X-Guest-Token`) |
| `POST` | `/api/cart/items` | Add variant to cart (`variant_id`, `quantity` `1..50`) | Optional |
| `PUT` / `PATCH` | `/api/cart/items/{item}` | Update item quantity (`quantity` `1..50`) | Optional |
| `DELETE` | `/api/cart/items/{item}` | Remove item from cart | Optional |
| `DELETE` | `/api/cart` | Clear all items and detach coupon | Optional |
| `PUT` / `POST` | `/api/cart/address` | Select saved `address_id` or set inline Bangladesh `shipping_address` | Optional (`address_id` requires auth) |
| `POST` | `/api/cart/coupon` | Validate and apply a coupon code (`code` / `coupon_code`) | Optional |
| `DELETE` | `/api/cart/coupon` | Remove applied coupon from cart | Optional |
| `POST` | `/api/cart/merge` | Idempotently merge `guest_token` cart into authenticated customer cart | Required (`auth:sanctum`) |

---

## 3. Checkout & Order Endpoints (`/api/checkout`)

| Method | Endpoint | Description | Status Codes |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/checkout/quote` | Generate a persisted `CheckoutQuote` (`quote_token`, 15-minute TTL) | `200`, `403`, `409`, `422` |
| `POST` | `/api/checkout/validate-coupon` | Validate coupon against a `subtotal_amount` in BDT paisa | `200`, `422` |
| `POST` | `/api/checkout/shipping-rates` | Calculate BDT shipping rates for Bangladesh `division` and `district` | `200`, `422` |
| `POST` | `/api/checkout/orders` | Place order idempotently (`cod` or online payment preparation) | `201` (created), `200` (idempotent replay), `403`, `409`, `422` |
| `GET` | `/api/checkout/orders/{orderNumber}` | Retrieve order snapshot (enforces `OrderPolicy` ownership) | `200`, `403`, `404` |
| `GET` | `/api/account/orders` | List authenticated customer's order history | `200`, `401` |

### Error Semantics
- `403 Forbidden`: Attempting to access or convert another user's or guest's `cart_id`, `quote_token`, or `orderNumber`.
- `409 Conflict` (`PriceChangedException`): Variant price or total changed between quote/cart snapshot and order placement.
- `422 Unprocessable Entity`:
  - `InvalidVariantException`: Variant does not exist, is inactive, or belongs to an unpublished product.
  - `InsufficientStockException`: Variant is out of stock or available quantity is below requested quantity.
  - `CouponExpiredException` / `InvalidCouponException`: Coupon is expired, inactive, below `min_order_amount`, or exceeded usage limits.
  - `QuoteExpiredException`: `quote_token` has expired (`expires_at <= now()`).

