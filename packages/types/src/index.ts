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

export type OrderStatus =
  | 'pending'
  | 'payment_processing'
  | 'paid'
  | 'processing'
  | 'shipped'
  | 'delivered'
  | 'cancelled'
  | 'refunded';

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
