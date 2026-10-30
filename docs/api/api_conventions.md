# API Conventions Record

## 1. Base URL & Versioning

All HTTP REST endpoints must strictly include explicit versioning in the URL path.

```
Base URL: https://api.yourstore.com/api/v1
```

---

## 2. Standardized Response Formats

### Successful Response Format (`200 OK`, `201 Created`)

```json
{
  "success": true,
  "message": "Resource retrieved successfully.",
  "data": {
    "id": 501,
    "sku": "TSH-BLK-M",
    "color": "Black",
    "size": "M",
    "price": "1200.00",
    "currency": "BDT"
  },
  "meta": null
}
```

### Paginated List Response Format (`200 OK`)

```json
{
  "success": true,
  "message": "Products fetched successfully.",
  "data": [
    { "id": 101, "name": "Classic Oxford Shirt", "slug": "classic-oxford-shirt" }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 72
  }
}
```

### Error Response Formats

#### Validation Error (`422 Unprocessable Entity`)
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "phone": [
      "The phone number format must be a valid Bangladesh mobile number (+8801XXXXXXXXX)."
    ]
  }
}
```

#### Generic Error (`400 Bad Request`, `401 Unauthenticated`, `403 Forbidden`, `404 Not Found`, `409 Conflict`)
```json
{
  "success": false,
  "message": "The requested resource was not found or has been removed.",
  "errors": null
}
```

---

## 3. HTTP Status Code Guidelines

| Code | Usage Scenario |
| :--- | :--- |
| `200 OK` | Successful fetch, update, or non-creation action. |
| `201 Created` | Successful entity creation. |
| `202 Accepted` | Async task accepted (e.g. bulk import queued). |
| `204 No Content` | Successful deletion without payload body. |
| `400 Bad Request` | Malformed payload or broken request parameters. |
| `401 Unauthenticated` | User lacks valid session cookie or token authentication. |
| `403 Unauthorized` | User authenticated but lacks RBAC permission. |
| `404 Not Found` | Requested route or database record does not exist. |
| `409 Conflict` | Domain conflict (e.g. duplicate payment callback, stock mismatch). |
| `422 Unprocessable` | Input validation failed against FormRequest rules. |
| `429 Rate Limited` | Exceeded API rate limit quota. |