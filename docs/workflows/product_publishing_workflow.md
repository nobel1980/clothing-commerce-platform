# Product Publishing Workflow

## Overview

A product must pass validation before becoming publicly visible in the storefront. This workflow ensures data integrity, catalog consistency, and storefront cache coherence.

---

## Workflow Diagram

```
Admin creates product
        │
        ▼
Add category and description
        │
        ▼
Upload product images
        │
        ▼
Create colors and sizes (variants)
        │
        ▼
Generate variant SKUs
        │
        ▼
Set variant prices
        │
        ▼
Configure inventory
        │
        ▼
   Validation passed?
    ┌───┴───┐
   No      Yes
    │        │
    ▼        ▼
 Show      Publish product
 errors         │
                ▼
        Invalidate catalog cache
                │
                ▼
        Product visible in storefront
```

---

## Stage 1: Product Creation (Draft)

**Actor:** Admin (product_manager or super_admin)

| Field       | Required | Notes                              |
|-------------|----------|------------------------------------|
| name        | Yes      | Display name                       |
| slug        | Yes      | Auto-generated, unique, SEO-safe   |
| description | Yes      | Full product description           |
| category_id | Yes      | Must reference existing category   |
| brand_id    | No       | Optional brand association         |
| status      | Auto     | Defaults to `draft`                |
| is_featured | No       | Defaults to `false`                |

---

## Stage 2: Image Upload

- Minimum 1 image required before publishing.
- Accepted formats: `JPEG`, `PNG`, `WebP`.
- Max file size: 5MB per image.
- Images stored in object storage (local or S3-compatible).
- First uploaded image is used as the primary thumbnail.

---

## Stage 3: Variant Creation

Each sellable variant requires:

| Field            | Required | Notes                         |
|------------------|----------|-------------------------------|
| sku              | Yes      | Unique across all variants    |
| color            | No       | Display color label           |
| size             | No       | Display size label            |
| price            | Yes      | Selling price in BDT          |
| compare_at_price | No       | Original / crossed-out price  |
| cost_price       | No       | Internal cost (not displayed) |
| status           | Yes      | `active` or `inactive`        |

> **Rule:** A product must have at least one `active` variant to be publishable.

---

## Stage 4: Inventory Configuration

- For each active variant, an `inventory_items` record must be created.
- Minimum `quantity_on_hand` may be zero (out-of-stock products are still publishable).
- `reorder_level` alerts are optional at creation time.

---

## Stage 5: Publish Validation

Before `POST /admin/products/{id}/publish` succeeds, the system validates:

- [ ] `name` is not blank
- [ ] `description` is not blank
- [ ] `category_id` references an existing category
- [ ] At least one product image exists
- [ ] At least one variant with `status = active` and valid `price > 0` exists
- [ ] All variant SKUs are unique

**On failure:** Returns `HTTP 422` with field-level validation errors.  
**On success:** Sets `status = 'active'`, sets `published_at = now()`.

---

## Stage 6: Cache Invalidation

After successful publish:

1. Invalidate the product detail cache for `/catalog/products/{slug}`.
2. Invalidate the category listing cache containing this product.
3. If `is_featured = true`, invalidate the featured products cache.

> Cache invalidation must be dispatched as a queued job to avoid blocking the HTTP response.

---

## Product Status Transitions

```
draft ──────────────────► active (published)
  ▲                            │
  │                            ▼
  └──────────────────── archived
```

| From     | To       | Trigger                            | Permission         |
|----------|----------|------------------------------------|--------------------|
| draft    | active   | `POST /admin/products/{id}/publish`| product_manager    |
| active   | archived | `POST /admin/products/{id}/archive`| product_manager    |
| archived | draft    | `POST /admin/products/{id}/restore`| product_manager    |

---

## Rules & Guards

- Only `active` products appear in public catalog APIs.
- `draft` and `archived` products are only accessible via admin APIs.
- Updating a published product does not unpublish it.
- Price changes on published products take effect immediately.
- SKU changes on published variants must be audited.
- Deleting a product with existing order items is **prohibited** — archive instead.

---

## API Endpoints

| Method | Endpoint                              | Purpose                    |
|--------|---------------------------------------|----------------------------|
| POST   | `/api/v1/admin/products`              | Create a draft product     |
| PUT    | `/api/v1/admin/products/{id}`         | Update product details     |
| POST   | `/api/v1/admin/products/{id}/images`  | Upload product images      |
| POST   | `/api/v1/admin/products/{id}/publish` | Publish the product        |
| POST   | `/api/v1/admin/products/{id}/archive` | Archive a product          |
| DELETE | `/api/v1/admin/products/{id}/images/{imageId}` | Delete an image  |

---

## Events Emitted

| Event            | When                         | Downstream Effect             |
|------------------|------------------------------|-------------------------------|
| `ProductCreated` | On draft creation            | Audit log                     |
| `ProductUpdated` | On any field change          | Cache invalidation job        |
| `ProductPublished` | On publish                 | Cache invalidation, sitemap   |
| `ProductArchived`  | On archive                 | Remove from public catalog    |
