# Centralized Admin Dashboard & Governance API Documentation

The **Centralized Admin Dashboard** spans 14 operational modules across `apps/web/src/app/(admin)/admin/*` and `apps/api/routes/api.php`, enforcing both server-side Spatie permission checks (`auth:sanctum` + `staff` + granular permission verification) and client-side permission-aware navigation.

---

## 1. Module & Permission Matrix

Every `/api/admin/*` endpoint and `/admin/*` UI route is protected by explicit Spatie permissions:

| Module | Frontend Route | Read Permission | Write / Mutate Permission | Primary Backend Endpoints |
| :--- | :--- | :--- | :--- | :--- |
| **Dashboard** | `/admin` | `reports.view` or `orders.view` | — | `GET /api/admin/dashboard/metrics` |
| **Products** | `/admin/products` | `catalog.view` | `catalog.manage` | `GET/POST/PUT/DELETE /api/admin/catalog/products` |
| **Categories** | `/admin/categories` | `catalog.view` | `catalog.manage` | `GET/POST/PUT/DELETE /api/admin/catalog/categories` |
| **Variants** | `/admin/variants` | `catalog.view` | `catalog.manage` | `GET /api/admin/catalog/variants`<br>`POST/PUT/DELETE /api/admin/catalog/products/{product}/variants` |
| **Inventory** | `/admin/inventory` | `inventory.view` | `inventory.manage` | `GET /api/admin/inventory/items`<br>`POST /api/admin/inventory/adjust`<br>`GET /api/admin/inventory/movements` |
| **Orders** | `/admin/orders` | `orders.view` | `orders.manage` | `GET /api/admin/orders`<br>`POST /api/admin/orders/{order}/{confirm\|process\|ship\|deliver\|cancel}` |
| **Payments** | `/admin/payments` | `payments.view` | `payments.manage` | `GET /api/admin/payments`<br>`GET /api/admin/payments/refunds`<br>`POST /api/admin/payments/{transaction}/refund` |
| **Customers** | `/admin/customers` | `customers.view` | `customers.manage` | `GET /api/admin/customers`<br>`GET/PUT /api/admin/customers/{customer}`<br>`POST /api/admin/customers/{customer}/status` |
| **Promotions** | `/admin/promotions` | `promotions.view` | `promotions.manage` | `GET/POST/PUT/DELETE /api/admin/promotions/coupons`<br>`POST /api/admin/promotions/coupons/{coupon}/toggle` |
| **Shipping** | `/admin/shipping` | `shipping.view` | `shipping.manage` | `GET/POST /api/admin/shipments`<br>`POST /api/admin/shipments/{shipment}/{track\|cancel}`<br>`GET/POST /api/admin/shipments/cod-reconciliation` |
| **Reviews** | `/admin/reviews` | `reviews.view` | `reviews.manage` | `GET /api/admin/reviews`<br>`POST /api/admin/reviews/{review}/moderate`<br>`DELETE /api/admin/reviews/{review}` |
| **Reports** | `/admin/reports` | `reports.view` | — | `GET /api/admin/reports/overview`<br>`GET /api/admin/reports/{sales\|best-sellers\|low-stock}` |
| **Settings** | `/admin/settings` | `settings.view` | `settings.manage` | `GET /api/admin/settings`<br>`PUT /api/admin/settings` |
| **Users & RBAC** | `/admin/users` | `users.view`, `roles.view`, `audit.view` | `users.manage`, `roles.manage` | `GET/POST/PUT/DELETE /api/admin/users`<br>`GET/POST/PUT/DELETE /api/admin/roles`<br>`GET /api/admin/audit-logs` |

---

## 2. Dashboard Metrics Endpoint (`GET /api/admin/dashboard/metrics`)

Computes real-time metrics from the relational database in integer BDT paisa (never hardcoded):

```json
{
  "data": {
    "currency": "BDT",
    "total_sales": 967000,
    "total_sales_count": 2,
    "orders_today": 2,
    "pending_orders": 1,
    "delivered_orders": 1,
    "revenue": 640000,
    "average_order_value": 483500,
    "low_stock_count": 1,
    "low_stock_products": [
      {
        "inventory_item_id": 1,
        "product_id": 1,
        "product_name": "Embroidered Cotton Panjabi",
        "product_variant_id": 1,
        "sku": "PNJ-BLK-L",
        "color": "Black",
        "size": "L",
        "warehouse_id": 1,
        "warehouse_name": "Dhaka Central Hub",
        "warehouse_code": "DHK-CENTRAL",
        "quantity_on_hand": 3,
        "quantity_reserved": 0,
        "available_quantity": 3,
        "low_stock_threshold": 5,
        "is_out_of_stock": false
      }
    ],
    "best_selling_products": [
      {
        "product_id": 1,
        "product_name": "Embroidered Cotton Panjabi",
        "sku": "PNJ-BLK-L",
        "total_quantity_sold": 3,
        "total_revenue": 960000,
        "orders_count": 2,
        "currency": "BDT"
      }
    ]
  }
}
```

---

## 3. CSV Export Endpoint (`GET /api/admin/export/{resource}`)

Exports filtered domain datasets as RFC 4180 CSV (`text/csv; charset=UTF-8`) while enforcing the resource's domain `.view` permission and logging `export.{resource}` in `audit_logs`.

Supported `{resource}` values:
- `orders` (`orders.view`)
- `products` (`catalog.view`)
- `categories` (`catalog.view`)
- `variants` (`catalog.view`)
- `inventory` (`inventory.view`)
- `customers` (`customers.view`)
- `payments` (`payments.view`)
- `promotions` (`promotions.view`)
- `shipments` (`shipping.view`)
- `reviews` (`reviews.view`)
- `audit-logs` (`audit.view`)
- `reports-sales` (`reports.view`)

