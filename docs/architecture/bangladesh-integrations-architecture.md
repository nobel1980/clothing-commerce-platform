# Bangladesh Integrations Architecture Record

## 1. Localized Address Hierarchy

Addresses in Bangladesh require explicit regional categorization rather than open-ended string inputs to integrate seamlessly with local courier partners.

```
customer_addresses
├── id
├── user_id
├── recipient_name
├── phone               # E.164 format (+8801XXXXXXXXX)
├── division            # e.g., Dhaka, Chattogram, Rajshahi
├── district            # e.g., Dhaka, Gazipur, Narayanganj
├── upazila/thana       # e.g., Dhanmondi, Uttara, Mirpur
├── area                # Local area/sub-zone
├── address_line        # House, road, block details
├── postal_code
└── delivery_instruction
```

---

## 2. Payment Gateway Adapter Abstraction

The `Payments` module defines a strict polymorphic adapter contract to support mobile financial services (MFS) and traditional gateways.

```php
namespace App\Modules\Payments\Domain\Contracts;

interface PaymentGateway
{
    public function initiate(PaymentRequest $request): PaymentInitiationResult;
    public function verify(string $gatewayReference): PaymentVerificationResult;
    public function refund(RefundRequest $request): RefundResult;
}
```

### Supported Provider Adapters:
* `BkashAdapter`: bKash Direct Checkout API.
* `NagadAdapter`: Nagad Merchant API.
* `SslCommerzAdapter`: SSLCommerz Gateway (Cards / Net Banking).
* `CodAdapter`: Internal Cash-on-Delivery processing.

### Security Rules:
* All callback and webhook verification MUST happen server-to-server.
* Never trust client-side URL redirects to confirm payment status.

---

## 3. Courier & Fulfillment Adapter Abstraction

Shipping and fulfillment utilize a provider adapter pattern to standardize consignment creation, tracking, and COD collection reconciliation across logistics partners.

```php
namespace App\Modules\Shipping\Domain\Contracts;

interface CourierProvider
{
    public function calculateFee(ShippingQuoteRequest $request): Money;
    public function createConsignment(ShipmentRequest $request): ConsignmentResult;
    public function trackConsignment(string $trackingCode): TrackingStatus;
    public function cancelConsignment(string $trackingCode): bool;
}
```

### Integrated Logistics Partners:
* **Pathao Courier** (Merchant API v2)
* **Steadfast Courier**
* **REDX Courier**

### Courier vs Internal Order Status Mapping:
Local courier statuses must be mapped to normalized internal shipment states (`pending`, `picked_up`, `in_transit`, `delivered`, `failed`, `returned_to_merchant`).