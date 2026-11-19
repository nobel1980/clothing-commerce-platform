/**
 * Shared Type Definitions & Contracts
 * Clothing Commerce Platform
 */

export interface Money {
  amount: number;       // In smallest currency unit, e.g. cents
  currency: string;     // ISO 4217, e.g. "USD", "EUR"
  formatted?: string;   // e.g. "$49.99"
}

export type ProductStatus = 'draft' | 'published' | 'archived';

export interface Category {
  id: string;
  name: string;
  slug: string;
  description?: string;
  parentId?: string | null;
  createdAt: string;
  updatedAt: string;
}

export interface ProductVariant {
  id: string;
  productId: string;
  sku: string;
  size: string;
  color: string;
  price: Money;
  compareAtPrice?: Money;
  stockQuantity: number;
}

export interface Product {
  id: string;
  name: string;
  slug: string;
  description: string;
  categories: Category[];
  variants: ProductVariant[];
  basePrice: Money;
  status: ProductStatus;
  images: string[];
  createdAt: string;
  updatedAt: string;
}

export interface CartItem {
  id: string;
  variantId: string;
  productId: string;
  name: string;
  size: string;
  color: string;
  quantity: number;
  unitPrice: Money;
  totalPrice: Money;
  image?: string;
}

export interface Cart {
  id: string;
  userId?: string | null;
  items: CartItem[];
  subtotal: Money;
  tax: Money;
  shipping: Money;
  discount: Money;
  total: Money;
}

/**
 * Order lifecycle status. Mirrors the Laravel `orders.status` column (lower-case);
 * the API returns it upper-cased, so clients must normalise before comparing.
 * Payment state is tracked separately in `PaymentStatus`.
 * `payment_processing`, `paid` and `refunded` are retained for backwards compatibility.
 */
export type OrderStatus =
  | 'pending'
  | 'pending_payment'
  | 'pending_confirmation'
  | 'awaiting_payment'
  | 'payment_failed'
  | 'confirmed'
  | 'processing'
  | 'shipped'
  | 'delivered'
  | 'cancelled'
  | 'returned'
  | 'payment_processing'
  | 'paid'
  | 'refunded';

export type PaymentStatus =
  | 'pending'
  | 'cod_pending'
  | 'awaiting_payment'
  | 'paid'
  | 'failed'
  | 'partially_refunded'
  | 'refunded';

export type PaymentGatewayId = 'cod' | 'bkash' | 'nagad' | 'sslcommerz';

export interface OrderItem {
  id: string;
  orderId: string;
  variantId: string;
  sku: string;
  productName: string;
  size: string;
  color: string;
  quantity: number;
  unitPrice: Money;
  totalPrice: Money;
}

export interface ShippingAddress {
  fullName: string;
  addressLine1: string;
  addressLine2?: string;
  division?: string;
  district?: string;
  upazila_thana?: string;
  city: string;
  state: string;
  postalCode: string;
  country: string;
  phone: string;
}

export interface Order {
  id: string;
  orderNumber: string;
  customerId: string;
  status: OrderStatus;
  /** Separate from `status`; absent on legacy payloads. */
  payment_status?: PaymentStatus;
  payment_method?: string;
  items: OrderItem[];
  shippingAddress: ShippingAddress;
  total: Money;
  createdAt: string;
  updatedAt: string;
}

export interface User {
  id: string;
  name: string;
  email: string;
  role: 'customer' | 'admin' | 'staff';
  emailVerifiedAt?: string | null;
  createdAt: string;
}

export interface HealthCheckResult {
  status: 'healthy' | 'degraded' | 'unhealthy';
  service: string;
  timestamp: string;
  version?: string;
  uptimeSeconds?: number;
  checks?: {
    database?: { status: 'ok' | 'fail'; message?: string };
    redis?: { status: 'ok' | 'fail'; message?: string };
    queue?: { status: 'ok' | 'fail'; message?: string };
  };
}

export interface ApiResponse<T = unknown> {
  success: boolean;
  data?: T;
  message?: string;
  errors?: Record<string, string[]>;
}

export type InventoryMovementType =
  | 'initial'
  | 'restock'
  | 'adjustment'
  | 'sale'
  | 'return'
  | 'damage'
  | 'write_off'
  | 'transfer_out'
  | 'transfer_in'
  | 'reservation'
  | 'reservation_release'
  | 'reservation_expired'
  | 'reservation_commit';

export type StockReservationStatus =
  | 'active'
  | 'committed'
  | 'released'
  | 'expired';

export interface Warehouse {
  id: number;
  name: string;
  code: string;
  address_line_1?: string | null;
  address_line_2?: string | null;
  city?: string | null;
  state?: string | null;
  postal_code?: string | null;
  country: string;
  phone?: string | null;
  email?: string | null;
  is_active: boolean;
  is_default: boolean;
  priority: number;
  created_at?: string;
  updated_at?: string;
}

export interface InventoryItem {
  id: number;
  product_variant_id: number;
  warehouse_id: number;
  quantity_on_hand: number;
  quantity_reserved: number;
  available_quantity: number;
  low_stock_threshold: number;
  reorder_point?: number | null;
  reorder_quantity?: number | null;
  bin_location?: string | null;
  is_low_stock: boolean;
  is_out_of_stock: boolean;
  low_stock_notified_at?: string | null;
  warehouse?: Warehouse;
  created_at?: string;
  updated_at?: string;
}

export interface InventoryMovement {
  id: number;
  inventory_item_id: number;
  product_variant_id: number;
  warehouse_id: number;
  type: InventoryMovementType;
  quantity_change: number;
  on_hand_delta: number;
  reserved_delta: number;
  quantity_before: number;
  quantity_after: number;
  reserved_before: number;
  reserved_after: number;
  available_before: number;
  available_after: number;
  reference_type?: string | null;
  reference_id?: string | null;
  reason?: string | null;
  notes?: string | null;
  performed_by?: number | null;
  created_at: string;
}

export interface StockReservationItem {
  id: number;
  stock_reservation_id: number;
  inventory_item_id: number;
  product_variant_id: number;
  warehouse_id: number;
  quantity: number;
}

export interface StockReservation {
  id: number;
  idempotency_key: string;
  reference_type?: string | null;
  reference_id?: string | null;
  status: StockReservationStatus;
  expires_at: string;
  committed_at?: string | null;
  released_at?: string | null;
  expired_at?: string | null;
  items: StockReservationItem[];
  metadata?: Record<string, unknown> | null;
  created_at?: string;
  updated_at?: string;
}

export interface Brand {
  id: number;
  name: string;
  slug: string;
  description?: string | null;
  logo_url?: string | null;
  website_url?: string | null;
  is_active?: boolean;
  created_at?: string;
  updated_at?: string;
}

export interface ProductImageItem {
  id: number;
  url: string;
  alt_text?: string | null;
  sort_order: number;
  is_primary: boolean;
}

export interface VariantPrice {
  amount: number;
  currency: string;
  display: number;
  formatted?: string;
}

export interface CatalogVariant {
  id: number;
  product_id?: number;
  product?: {
    id: number;
    name: string;
    slug: string;
    status: ProductStatus;
  } | null;
  sku: string;
  color: string | null;
  size: string | null;
  price_amount?: number;
  cost_price?: number | null;
  price: VariantPrice;
  compare_at_price?: VariantPrice | null;
  status: string;
  created_at?: string;
  updated_at?: string;
}

export interface CatalogCategory {
  id: number;
  parent_id?: number | null;
  name: string;
  slug: string;
  description?: string | null;
  image_url?: string | null;
  sort_order?: number;
  is_active?: boolean;
  meta_title?: string | null;
  meta_description?: string | null;
  parent?: CatalogCategory | null;
  children?: CatalogCategory[];
  created_at?: string;
  updated_at?: string;
}

export interface CatalogProduct {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  body?: string | null;
  status: ProductStatus;
  is_featured: boolean;
  published_at?: string | null;
  meta_title?: string | null;
  meta_description?: string | null;
  meta_keywords?: string | null;
  brand?: Brand | null;
  category?: CatalogCategory | null;
  images?: ProductImageItem[];
  variants?: CatalogVariant[];
  created_at?: string;
  updated_at?: string;
}

export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from?: number | null;
  to?: number | null;
}

export interface PaginatedResponse<T> {
  data: T[];
  meta?: PaginationMeta;
  links?: {
    first?: string | null;
    last?: string | null;
    prev?: string | null;
    next?: string | null;
  };
}

export type ProductSortOption =
  | 'newest'
  | 'oldest'
  | 'price_asc'
  | 'price_desc'
  | 'name_asc';

export interface ProductFilterParams {
  search?: string;
  category?: string;
  brand?: string;
  color?: string;
  size?: string;
  min_price?: number;
  max_price?: number;
  featured?: boolean;
  sort?: ProductSortOption;
  page?: number;
  per_page?: number;
}

export interface VariantAvailability {
  product_variant_id: number;
  sku: string;
  in_stock: boolean;
  available_quantity: number;
  is_low_stock: boolean;
}

export interface CheckoutQuote {
  id: string;
  items: CartItem[];
  subtotal: Money;
  discount: Money;
  shipping: Money;
  tax: Money;
  total: Money;
  promoCode?: string | null;
  reservationKey?: string | null;
  expiresAt?: string | null;
}

export interface CustomerAddress {
  id: number;
  user_id?: number;
  name: string;
  phone: string;
  division: string;
  district: string;
  upazila_thana: string;
  street_address: string;
  postal_code?: string | null;
  formatted_address?: string;
  type: 'home' | 'office' | 'other';
  is_default: boolean;
  delivery_instructions?: string | null;
  created_at?: string;
  updated_at?: string;
}

export interface CustomerProfile {
  id: number;
  name: string;
  email: string;
  phone?: string | null;
  role: string;
  status: string;
  email_verified_at?: string | null;
  created_at?: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Centralized Admin Dashboard Contracts
// ─────────────────────────────────────────────────────────────────────────────

export type AdminPermission =
  | 'users.view'
  | 'users.manage'
  | 'roles.view'
  | 'roles.manage'
  | 'audit.view'
  | 'catalog.view'
  | 'catalog.manage'
  | 'inventory.view'
  | 'inventory.manage'
  | 'orders.view'
  | 'orders.manage'
  | 'customers.view'
  | 'customers.manage'
  | 'payments.view'
  | 'payments.manage'
  | 'shipping.view'
  | 'shipping.manage'
  | 'promotions.view'
  | 'promotions.manage'
  | 'reviews.view'
  | 'reviews.manage'
  | 'reports.view'
  | 'settings.view'
  | 'settings.manage';

export interface AdminStaffUser {
  id: number;
  name: string;
  email: string;
  phone?: string | null;
  role: string;
  status: 'active' | 'suspended' | 'inactive';
  roles?: string[];
  permissions?: string[];
  email_verified_at?: string | null;
  last_login_at?: string | null;
  created_at?: string;
}

export interface AdminRoleRecord {
  id: number;
  name: string;
  guard_name?: string;
  permissions: Array<{ id: number; name: string } | string>;
  users_count?: number;
  created_at?: string;
}

export interface AdminAuditLogRecord {
  id: number;
  user_id?: number | null;
  user_email?: string | null;
  action: string;
  auditable_type?: string | null;
  auditable_id?: number | string | null;
  old_values?: Record<string, unknown> | null;
  new_values?: Record<string, unknown> | null;
  ip_address?: string | null;
  user_agent?: string | null;
  created_at: string;
}

export interface AdminLowStockProduct {
  inventory_item_id: number;
  product_id?: number | null;
  product_name: string;
  product_slug?: string | null;
  product_variant_id: number;
  sku: string;
  color?: string | null;
  size?: string | null;
  warehouse_id: number;
  warehouse_name: string;
  warehouse_code: string;
  quantity_on_hand: number;
  quantity_reserved: number;
  available_quantity: number;
  low_stock_threshold: number;
  is_out_of_stock: boolean;
}

export interface AdminBestSellingProduct {
  product_id?: number | null;
  product_name: string;
  sku: string;
  total_quantity_sold: number;
  total_revenue: number;
  orders_count: number;
  currency: string;
}

export interface AdminRecentOrderSummary {
  id: number;
  order_number: string;
  customer_name: string;
  status: string;
  payment_status: string;
  fulfilment_status: string;
  payment_method: string;
  total_amount: number;
  currency: string;
  placed_at?: string | null;
}

export interface AdminDashboardMetrics {
  currency: string;
  total_sales: number;
  total_sales_count: number;
  orders_today: number;
  pending_orders: number;
  delivered_orders: number;
  revenue: number;
  average_order_value: number;
  low_stock_count: number;
  low_stock_products: AdminLowStockProduct[];
  best_selling_products: AdminBestSellingProduct[];
  status_breakdown: Array<{
    status: string;
    count: number;
    total_amount: number;
  }>;
  recent_orders: AdminRecentOrderSummary[];
  counts?: {
    total_products: number;
    total_customers: number;
    pending_reviews: number;
    active_shipments: number;
  };
}

export interface AdminOrderRecord {
  id: number;
  order_number: string;
  user_id?: number | null;
  status: string;
  payment_status: string;
  fulfilment_status: string;
  payment_method: string;
  currency: string;
  subtotal_amount: number;
  discount_amount: number;
  shipping_amount: number;
  tax_amount: number;
  total_amount: number;
  coupon_code?: string | null;
  shipping_method?: string | null;
  shipping_address?: {
    recipient_name?: string;
    phone?: string;
    division?: string;
    district?: string;
    upazila?: string;
    address_line_1?: string;
  } | null;
  items?: Array<{
    id: number;
    product_id?: number | null;
    product_variant_id?: number | null;
    sku: string;
    product_name: string;
    color?: string | null;
    size?: string | null;
    quantity: number;
    unit_price_amount: number;
    line_total_amount: number;
  }>;
  status_histories?: Array<{
    id: number;
    from_status?: string | null;
    to_status: string;
    payment_status?: string | null;
    fulfilment_status?: string | null;
    actor_type: string;
    notes?: string | null;
    created_at: string;
  }>;
  placed_at?: string | null;
  confirmed_at?: string | null;
  shipped_at?: string | null;
  delivered_at?: string | null;
  cancelled_at?: string | null;
  cancellation_reason?: string | null;
}

export interface AdminPaymentTransaction {
  id: number;
  order_id: number;
  order_number?: string | null;
  order_status?: string | null;
  merchant_reference: string;
  gateway: string;
  gateway_payment_id?: string | null;
  gateway_transaction_id?: string | null;
  amount: number;
  refunded_amount: number;
  currency: string;
  status: string;
  failure_reason?: string | null;
  paid_at?: string | null;
  failed_at?: string | null;
  refunded_at?: string | null;
  created_at?: string | null;
  refunds?: Array<{
    id: number;
    amount: number;
    status: string;
    reason?: string | null;
    gateway_refund_id?: string | null;
    refunded_at?: string | null;
  }>;
}

export interface AdminPaymentSummary {
  total_paid_amount: number;
  total_refunded_amount: number;
  paid_count: number;
  pending_count: number;
  failed_count: number;
}

export interface AdminCustomerRecord {
  id: number;
  name: string;
  email: string;
  phone?: string | null;
  status: 'active' | 'suspended' | 'inactive';
  orders_count: number;
  total_spent: number;
  currency: string;
  email_verified_at?: string | null;
  last_login_at?: string | null;
  created_at?: string | null;
  recent_orders?: AdminRecentOrderSummary[];
}

export interface AdminCouponRecord {
  id: number;
  code: string;
  description?: string | null;
  type: 'percentage' | 'fixed_amount';
  value: number;
  min_order_amount: number;
  max_discount_amount?: number | null;
  usage_limit?: number | null;
  usage_limit_per_user?: number | null;
  used_count: number;
  redemptions_count?: number;
  currency: string;
  starts_at?: string | null;
  expires_at?: string | null;
  is_active: boolean;
  is_expired: boolean;
  created_at?: string | null;
}

export interface AdminShipmentRecord {
  id: number;
  order_id: number;
  order_number?: string | null;
  courier: string;
  consignment_id?: string | null;
  tracking_number?: string | null;
  status: string;
  delivery_fee: number;
  cod_amount: number;
  cod_collected_amount?: number | null;
  cod_reconciliation_status: string;
  recipient_name?: string | null;
  recipient_phone?: string | null;
  recipient_division?: string | null;
  recipient_district?: string | null;
  shipped_at?: string | null;
  delivered_at?: string | null;
  returned_at?: string | null;
  cancelled_at?: string | null;
  tracking_events?: Array<{
    id: number;
    external_status: string;
    internal_status: string;
    description?: string | null;
    location?: string | null;
    occurred_at?: string | null;
  }>;
}

export interface AdminReviewRecord {
  id: number;
  product_id: number;
  product?: {
    id: number;
    name: string;
    slug: string;
  } | null;
  user_id?: number | null;
  order_id?: number | null;
  reviewer_name: string;
  reviewer_email?: string | null;
  rating: number;
  title?: string | null;
  comment: string;
  status: 'pending' | 'approved' | 'rejected';
  is_verified_purchase: boolean;
  moderation_notes?: string | null;
  moderated_at?: string | null;
  created_at?: string | null;
}

export interface AdminPlatformSetting {
  id: number;
  group: string;
  key: string;
  value: string | number | boolean | Record<string, unknown> | null;
  type: 'string' | 'integer' | 'boolean' | 'json';
  label: string;
  description?: string | null;
  is_public: boolean;
  updated_at?: string | null;
}

export interface AdminComprehensiveReport {
  metrics: AdminDashboardMetrics;
  payment_method_breakdown: Array<{
    payment_method: string;
    orders_count: number;
    total_amount: number;
  }>;
  daily_sales: Array<{
    date: string;
    orders_count: number;
    total_sales: number;
    revenue: number;
  }>;
}



