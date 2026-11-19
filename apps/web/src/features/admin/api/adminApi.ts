import type {
  AdminAuditLogRecord,
  AdminBestSellingProduct,
  AdminComprehensiveReport,
  AdminCouponRecord,
  AdminCustomerRecord,
  AdminDashboardMetrics,
  AdminLowStockProduct,
  AdminOrderRecord,
  AdminPaymentSummary,
  AdminPaymentTransaction,
  AdminPlatformSetting,
  AdminReviewRecord,
  AdminRoleRecord,
  AdminShipmentRecord,
  AdminStaffUser,
  Brand,
  CatalogCategory,
  CatalogProduct,
  CatalogVariant,
  InventoryItem,
  InventoryMovement,
  PaginatedResponse,
  Warehouse,
} from '@clothing-commerce/types';
import { apiRequest, getApiBaseUrl } from '@/lib/api/client';

function buildQuery(params: Record<string, string | number | boolean | undefined | null>): string {
  const sp = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== null && value !== '') {
      sp.set(key, String(value));
    }
  }
  const qs = sp.toString();
  return qs ? `?${qs}` : '';
}

// ─── Admin Auth ──────────────────────────────────────────────────────────────

export async function loginAdminStaff(payload: {
  email: string;
  password: string;
}): Promise<{ data: AdminStaffUser; token?: string; message?: string }> {
  return apiRequest('/admin/auth/login', {
    method: 'POST',
    body: payload,
    isPrivate: true,
  });
}

export async function fetchAdminMe(): Promise<{ data: AdminStaffUser }> {
  return apiRequest('/admin/auth/me', { isPrivate: true });
}

export async function logoutAdminStaff(): Promise<{ message: string }> {
  return apiRequest('/admin/auth/logout', {
    method: 'POST',
    isPrivate: true,
  });
}

// ─── Dashboard Metrics ───────────────────────────────────────────────────────

export async function fetchAdminDashboardMetrics(params?: {
  from_date?: string;
  to_date?: string;
}): Promise<{ data: AdminDashboardMetrics }> {
  return apiRequest(`/admin/dashboard/metrics${buildQuery(params ?? {})}`, {
    isPrivate: true,
  });
}

// ─── Catalog: Products, Categories, Variants & Brands ────────────────────────

export async function fetchAdminProducts(params: {
  search?: string;
  status?: string;
  category?: string;
  brand?: string;
  page?: number;
  per_page?: number;
}): Promise<PaginatedResponse<CatalogProduct>> {
  return apiRequest(`/admin/catalog/products${buildQuery(params)}`, {
    isPrivate: true,
  });
}

export async function createAdminProduct(payload: {
  name: string;
  slug?: string;
  description?: string;
  category_id?: number | null;
  brand_id?: number | null;
  status?: 'draft' | 'published' | 'archived';
  is_featured?: boolean;
}): Promise<{ data: CatalogProduct }> {
  return apiRequest('/admin/catalog/products', {
    method: 'POST',
    body: payload,
    isPrivate: true,
  });
}

export async function updateAdminProduct(
  productId: number,
  payload: Partial<{
    name: string;
    slug: string;
    description: string;
    category_id: number | null;
    brand_id: number | null;
    status: 'draft' | 'published' | 'archived';
    is_featured: boolean;
  }>
): Promise<{ data: CatalogProduct }> {
  return apiRequest(`/admin/catalog/products/${productId}`, {
    method: 'PUT',
    body: payload,
    isPrivate: true,
  });
}

export async function deleteAdminProduct(productId: number): Promise<{ message: string }> {
  return apiRequest(`/admin/catalog/products/${productId}`, {
    method: 'DELETE',
    isPrivate: true,
  });
}

export async function publishAdminProduct(productId: number): Promise<{ data: CatalogProduct }> {
  return apiRequest(`/admin/catalog/products/${productId}/publish`, {
    method: 'POST',
    isPrivate: true,
  });
}

export async function unpublishAdminProduct(productId: number): Promise<{ data: CatalogProduct }> {
  return apiRequest(`/admin/catalog/products/${productId}/unpublish`, {
    method: 'POST',
    isPrivate: true,
  });
}

export async function fetchAdminCategories(params: {
  search?: string;
  is_active?: boolean | string;
  page?: number;
  per_page?: number;
}): Promise<PaginatedResponse<CatalogCategory>> {
  return apiRequest(`/admin/catalog/categories${buildQuery(params)}`, {
    isPrivate: true,
  });
}

export async function createAdminCategory(payload: {
  name: string;
  slug?: string;
  description?: string;
  parent_id?: number | null;
  sort_order?: number;
  is_active?: boolean;
}): Promise<{ data: CatalogCategory }> {
  return apiRequest('/admin/catalog/categories', {
    method: 'POST',
    body: payload,
    isPrivate: true,
  });
}

export async function updateAdminCategory(
  categoryId: number,
  payload: Partial<{
    name: string;
    slug: string;
    description: string;
    parent_id: number | null;
    sort_order: number;
    is_active: boolean;
  }>
): Promise<{ data: CatalogCategory }> {
  return apiRequest(`/admin/catalog/categories/${categoryId}`, {
    method: 'PUT',
    body: payload,
    isPrivate: true,
  });
}

export async function deleteAdminCategory(categoryId: number): Promise<{ message: string }> {
  return apiRequest(`/admin/catalog/categories/${categoryId}`, {
    method: 'DELETE',
    isPrivate: true,
  });
}

export async function fetchAdminVariants(params: {
  search?: string;
  status?: string;
  product_id?: number;
  color?: string;
  size?: string;
  page?: number;
  per_page?: number;
}): Promise<PaginatedResponse<CatalogVariant>> {
  return apiRequest(`/admin/catalog/variants${buildQuery(params)}`, {
    isPrivate: true,
  });
}

export async function createAdminVariant(
  productId: number,
  payload: {
    sku: string;
    color?: string;
    size?: string;
    price_amount: number;
    compare_at_price?: number | null;
    cost_price?: number | null;
    status?: 'active' | 'inactive' | 'discontinued';
  }
): Promise<{ data: CatalogVariant }> {
  return apiRequest(`/admin/catalog/products/${productId}/variants`, {
    method: 'POST',
    body: payload,
    isPrivate: true,
  });
}

export async function updateAdminVariant(
  productId: number,
  variantId: number,
  payload: Partial<{
    sku: string;
    color: string | null;
    size: string | null;
    price_amount: number;
    compare_at_price: number | null;
    status: 'active' | 'inactive' | 'discontinued';
  }>
): Promise<{ data: CatalogVariant }> {
  return apiRequest(`/admin/catalog/products/${productId}/variants/${variantId}`, {
    method: 'PUT',
    body: payload,
    isPrivate: true,
  });
}

export async function deleteAdminVariant(
  productId: number,
  variantId: number
): Promise<{ message: string }> {
  return apiRequest(`/admin/catalog/products/${productId}/variants/${variantId}`, {
    method: 'DELETE',
    isPrivate: true,
  });
}

export async function fetchAdminBrands(): Promise<{ data: Brand[] }> {
  return apiRequest('/admin/catalog/brands', { isPrivate: true });
}

// ─── Inventory ───────────────────────────────────────────────────────────────

export async function fetchAdminInventoryItems(params: {
  search?: string;
  warehouse_id?: number;
  low_stock?: boolean;
  out_of_stock?: boolean;
  page?: number;
  per_page?: number;
}): Promise<PaginatedResponse<InventoryItem & { product_variant?: CatalogVariant }>> {
  return apiRequest(`/admin/inventory/items${buildQuery(params)}`, {
    isPrivate: true,
  });
}

export async function fetchAdminWarehouses(): Promise<PaginatedResponse<Warehouse>> {
  return apiRequest('/admin/inventory/warehouses?per_page=50', {
    isPrivate: true,
  });
}

export async function adjustAdminStock(payload: {
  product_variant_id: number;
  warehouse_id: number;
  quantity_delta: number;
  type?: string;
  reason: string;
  notes?: string;
}): Promise<{ data: InventoryMovement }> {
  return apiRequest('/admin/inventory/adjust', {
    method: 'POST',
    body: payload,
    isPrivate: true,
  });
}

export async function fetchAdminInventoryMovements(params: {
  warehouse_id?: number;
  type?: string;
  page?: number;
  per_page?: number;
}): Promise<PaginatedResponse<InventoryMovement>> {
  return apiRequest(`/admin/inventory/movements${buildQuery(params)}`, {
    isPrivate: true,
  });
}

// ─── Orders ──────────────────────────────────────────────────────────────────

export async function fetchAdminOrders(params: {
  search?: string;
  status?: string;
  payment_status?: string;
  fulfilment_status?: string;
  payment_method?: string;
  from_date?: string;
  to_date?: string;
  page?: number;
  per_page?: number;
}): Promise<PaginatedResponse<AdminOrderRecord>> {
  return apiRequest(`/admin/orders${buildQuery(params)}`, {
    isPrivate: true,
  });
}

export async function transitionAdminOrder(
  orderId: number,
  action: 'confirm' | 'process' | 'ship' | 'deliver' | 'cancel',
  payload?: {
    notes?: string;
    reason?: string;
    carrier?: string;
    tracking_number?: string;
  }
): Promise<{ data: AdminOrderRecord }> {
  return apiRequest(`/admin/orders/${orderId}/${action}`, {
    method: 'POST',
    body: payload ?? {},
    isPrivate: true,
  });
}

// ─── Payments ────────────────────────────────────────────────────────────────

export async function fetchAdminPayments(params: {
  search?: string;
  gateway?: string;
  status?: string;
  page?: number;
  per_page?: number;
}): Promise<PaginatedResponse<AdminPaymentTransaction> & { summary?: AdminPaymentSummary }> {
  return apiRequest(`/admin/payments${buildQuery(params)}`, {
    isPrivate: true,
  });
}

export async function initiateAdminPaymentRefund(
  transactionId: number,
  payload: { amount: number; reason: string }
): Promise<{ message: string; data: { refund_id: number; status: string; amount: number } }> {
  return apiRequest(`/admin/payments/${transactionId}/refund`, {
    method: 'POST',
    body: payload,
    isPrivate: true,
  });
}

// ─── Customers ───────────────────────────────────────────────────────────────

export async function fetchAdminCustomers(params: {
  search?: string;
  status?: string;
  page?: number;
  per_page?: number;
}): Promise<PaginatedResponse<AdminCustomerRecord>> {
  return apiRequest(`/admin/customers${buildQuery(params)}`, {
    isPrivate: true,
  });
}

export async function fetchAdminCustomerDetail(
  customerId: number
): Promise<{ data: AdminCustomerRecord }> {
  return apiRequest(`/admin/customers/${customerId}`, {
    isPrivate: true,
  });
}

export async function updateAdminCustomerStatus(
  customerId: number,
  status: 'active' | 'suspended' | 'inactive'
): Promise<{ data: AdminCustomerRecord; message: string }> {
  return apiRequest(`/admin/customers/${customerId}/status`, {
    method: 'POST',
    body: { status },
    isPrivate: true,
  });
}

// ─── Promotions (Coupons) ────────────────────────────────────────────────────

export async function fetchAdminCoupons(params: {
  search?: string;
  type?: string;
  is_active?: string;
  page?: number;
  per_page?: number;
}): Promise<PaginatedResponse<AdminCouponRecord>> {
  return apiRequest(`/admin/promotions/coupons${buildQuery(params)}`, {
    isPrivate: true,
  });
}

export async function createAdminCoupon(payload: {
  code: string;
  description?: string;
  type: 'percentage' | 'fixed_amount';
  value: number;
  min_order_amount?: number;
  max_discount_amount?: number | null;
  usage_limit?: number | null;
  expires_at?: string | null;
  is_active?: boolean;
}): Promise<{ data: AdminCouponRecord }> {
  return apiRequest('/admin/promotions/coupons', {
    method: 'POST',
    body: payload,
    isPrivate: true,
  });
}

export async function toggleAdminCoupon(
  couponId: number
): Promise<{ data: AdminCouponRecord }> {
  return apiRequest(`/admin/promotions/coupons/${couponId}/toggle`, {
    method: 'POST',
    isPrivate: true,
  });
}

export async function deleteAdminCoupon(couponId: number): Promise<{ message: string }> {
  return apiRequest(`/admin/promotions/coupons/${couponId}`, {
    method: 'DELETE',
    isPrivate: true,
  });
}

// ─── Shipping ────────────────────────────────────────────────────────────────

export async function fetchAdminShipments(params: {
  search?: string;
  courier?: string;
  status?: string;
  cod_reconciliation_status?: string;
  page?: number;
  per_page?: number;
}): Promise<PaginatedResponse<AdminShipmentRecord>> {
  return apiRequest(`/admin/shipments${buildQuery(params)}`, {
    isPrivate: true,
  });
}

export async function createAdminShipment(payload: {
  order_id: number;
  courier: 'pathao' | 'steadfast' | 'redx';
  weight_grams?: number;
  notes?: string;
}): Promise<{ data: AdminShipmentRecord }> {
  return apiRequest('/admin/shipments', {
    method: 'POST',
    body: payload,
    isPrivate: true,
  });
}

export async function syncAdminShipmentTracking(
  shipmentId: number
): Promise<{ data: AdminShipmentRecord }> {
  return apiRequest(`/admin/shipments/${shipmentId}/track`, {
    method: 'POST',
    isPrivate: true,
  });
}

export async function cancelAdminShipment(
  shipmentId: number,
  reason: string
): Promise<{ data: AdminShipmentRecord }> {
  return apiRequest(`/admin/shipments/${shipmentId}/cancel`, {
    method: 'POST',
    body: { reason },
    isPrivate: true,
  });
}

export async function reconcileAdminCod(
  items: Array<{ consignment_id: string; collected_amount: number }>
): Promise<{ data: { processed: number; reconciled: number; discrepancies: number } }> {
  return apiRequest('/admin/shipments/cod-reconciliation', {
    method: 'POST',
    body: { items },
    isPrivate: true,
  });
}

// ─── Reviews ─────────────────────────────────────────────────────────────────

export async function fetchAdminReviews(params: {
  search?: string;
  status?: string;
  rating?: number | string;
  page?: number;
  per_page?: number;
}): Promise<PaginatedResponse<AdminReviewRecord>> {
  return apiRequest(`/admin/reviews${buildQuery(params)}`, {
    isPrivate: true,
  });
}

export async function moderateAdminReview(
  reviewId: number,
  payload: { status: 'approved' | 'rejected' | 'pending'; moderation_notes?: string }
): Promise<{ data: AdminReviewRecord }> {
  return apiRequest(`/admin/reviews/${reviewId}/moderate`, {
    method: 'POST',
    body: payload,
    isPrivate: true,
  });
}

export async function deleteAdminReview(reviewId: number): Promise<{ message: string }> {
  return apiRequest(`/admin/reviews/${reviewId}`, {
    method: 'DELETE',
    isPrivate: true,
  });
}

// ─── Reports ─────────────────────────────────────────────────────────────────

export async function fetchAdminReportOverview(params?: {
  from_date?: string;
  to_date?: string;
}): Promise<{ data: AdminComprehensiveReport }> {
  return apiRequest(`/admin/reports/overview${buildQuery(params ?? {})}`, {
    isPrivate: true,
  });
}

export async function fetchAdminBestSellers(params?: {
  from_date?: string;
  to_date?: string;
  limit?: number;
}): Promise<{ data: AdminBestSellingProduct[] }> {
  return apiRequest(`/admin/reports/best-sellers${buildQuery(params ?? {})}`, {
    isPrivate: true,
  });
}

export async function fetchAdminLowStockReport(params?: {
  limit?: number;
}): Promise<{ data: AdminLowStockProduct[] }> {
  return apiRequest(`/admin/reports/low-stock${buildQuery(params ?? {})}`, {
    isPrivate: true,
  });
}

// ─── Settings ────────────────────────────────────────────────────────────────

export async function fetchAdminSettings(group?: string): Promise<{
  data: AdminPlatformSetting[];
}> {
  return apiRequest(`/admin/settings${buildQuery({ group })}`, {
    isPrivate: true,
  });
}

export async function updateAdminSettings(
  settings: Array<{
    key: string;
    value: string | number | boolean | Record<string, unknown> | null;
    group?: string;
    type?: 'string' | 'integer' | 'boolean' | 'json';
    label?: string;
  }>
): Promise<{ message: string; data: AdminPlatformSetting[] }> {
  return apiRequest('/admin/settings', {
    method: 'PUT',
    body: { settings },
    isPrivate: true,
  });
}

// ─── Users, Roles, Permissions & Audit Trail ─────────────────────────────────

export async function fetchAdminUsers(params: {
  search?: string;
  role?: string;
  status?: string;
  page?: number;
  per_page?: number;
}): Promise<PaginatedResponse<AdminStaffUser>> {
  return apiRequest(`/admin/users${buildQuery(params)}`, {
    isPrivate: true,
  });
}

export async function createAdminUser(payload: {
  name: string;
  email: string;
  phone?: string;
  password: string;
  role: string;
  status?: 'active' | 'suspended' | 'inactive';
  verified?: boolean;
}): Promise<{ data: AdminStaffUser }> {
  return apiRequest('/admin/users', {
    method: 'POST',
    body: payload,
    isPrivate: true,
  });
}

export async function toggleAdminUserStatus(
  userId: number,
  status: 'active' | 'suspended' | 'inactive'
): Promise<{ data: AdminStaffUser }> {
  return apiRequest(`/admin/users/${userId}/status`, {
    method: 'POST',
    body: { status },
    isPrivate: true,
  });
}

export async function deleteAdminUser(userId: number): Promise<{ message: string }> {
  return apiRequest(`/admin/users/${userId}`, {
    method: 'DELETE',
    isPrivate: true,
  });
}

export async function fetchAdminRoles(): Promise<{ data: AdminRoleRecord[] }> {
  return apiRequest('/admin/roles', { isPrivate: true });
}

export async function fetchAdminPermissions(): Promise<{
  data: Array<{ id: number; name: string }>;
}> {
  return apiRequest('/admin/permissions', { isPrivate: true });
}

export async function createAdminRole(payload: {
  name: string;
  permissions: string[];
}): Promise<{ data: AdminRoleRecord }> {
  return apiRequest('/admin/roles', {
    method: 'POST',
    body: payload,
    isPrivate: true,
  });
}

export async function updateAdminRolePermissions(
  roleId: number,
  permissions: string[]
): Promise<{ data: AdminRoleRecord }> {
  return apiRequest(`/admin/roles/${roleId}`, {
    method: 'PUT',
    body: { permissions },
    isPrivate: true,
  });
}

export async function deleteAdminRole(roleId: number): Promise<{ message: string }> {
  return apiRequest(`/admin/roles/${roleId}`, {
    method: 'DELETE',
    isPrivate: true,
  });
}

export async function fetchAdminAuditLogs(params: {
  search?: string;
  action?: string;
  user_email?: string;
  page?: number;
  per_page?: number;
}): Promise<PaginatedResponse<AdminAuditLogRecord>> {
  return apiRequest(`/admin/audit-logs${buildQuery(params)}`, {
    isPrivate: true,
  });
}

// ─── CSV Export Helper ───────────────────────────────────────────────────────

export type AdminExportResource =
  | 'orders'
  | 'products'
  | 'categories'
  | 'variants'
  | 'inventory'
  | 'customers'
  | 'payments'
  | 'promotions'
  | 'shipments'
  | 'reviews'
  | 'audit-logs'
  | 'reports-sales';

export async function downloadAdminCsvExport(
  resource: AdminExportResource,
  fallbackRows?: Array<Record<string, string | number | boolean | null | undefined>>
): Promise<string> {
  const url = `${getApiBaseUrl()}/admin/export/${resource}`;
  const headers: Record<string, string> = {
    Accept: 'text/csv',
  };

  if (typeof window !== 'undefined') {
    try {
      const token = window.localStorage.getItem('ccp_admin_token');
      if (token) {
        headers['Authorization'] = `Bearer ${token}`;
      }
    } catch {
      // Ignore storage access errors
    }
  }

  try {
    const response = await fetch(url, {
      method: 'GET',
      headers,
      credentials: 'include',
      cache: 'no-store',
    });

    if (response.ok) {
      const csvText = await response.text();
      triggerBrowserDownload(csvText, `${resource}-export.csv`);
      return csvText;
    }
  } catch {
    // Fallback to client CSV generation if offline/sandboxed
  }

  if (fallbackRows && fallbackRows.length > 0 && fallbackRows[0]) {
    const keys = Object.keys(fallbackRows[0]);
    const lines = [
      keys.join(','),
      ...fallbackRows.map((row) =>
        keys
          .map((k) => {
            const cell = row[k] ?? '';
            const escaped = String(cell).replace(/"/g, '""');
            return `"${escaped}"`;
          })
          .join(',')
      ),
    ];
    const csvText = lines.join('\n');
    triggerBrowserDownload(csvText, `${resource}-export.csv`);
    return csvText;
  }

  return '';
}

function triggerBrowserDownload(csvContent: string, filename: string): void {
  if (typeof window === 'undefined' || typeof document === 'undefined') {
    return;
  }
  try {
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const objectUrl = URL.createObjectURL(blob);
    link.href = objectUrl;
    link.setAttribute('download', filename);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(objectUrl);
  } catch {
    // No-op in headless/SSR test environments
  }
}
