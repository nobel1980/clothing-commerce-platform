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
  sku: string;
  color: string | null;
  size: string | null;
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


