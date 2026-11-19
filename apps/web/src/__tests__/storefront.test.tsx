import * as React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import type { CatalogProduct, Order } from '@clothing-commerce/types';
import {
  formatMoney,
  buildCanonicalUrl,
  buildProductJsonLd,
} from '@/lib/utils/format';
import { apiRequest } from '@/lib/api/client';
import {
  fetchCustomerOrders,
  initiatePayment,
  isCancellableByCustomer,
  isOnlinePaymentMethod,
  normalizeOrder,
} from '@/features/orders/api/ordersApi';
import { ProductCard } from '@/features/catalog/components/ProductCard';
import { ProductImageGallery } from '@/features/catalog/components/ProductImageGallery';
import { ProductVariantSelector } from '@/features/catalog/components/ProductVariantSelector';
import { OrderHistoryView } from '@/features/orders/components/OrderHistoryView';
import { EmptyState } from '@/components/shared/EmptyState';
import { ErrorState } from '@/components/shared/ErrorState';
import { PaginationNav } from '@/components/shared/PaginationNav';
import { StoreProviders } from '@/components/layout/StoreProviders';
import type { AdminDashboardMetrics, AdminStaffUser } from '@clothing-commerce/types';
import {
  ADMIN_NAV_ITEMS,
  AdminAuthProvider,
  getPermittedNavItems,
  userHasPermission,
} from '@/features/admin/hooks/useAdminAuth';
import { AdminShell } from '@/features/admin/components/AdminShell';
import { ConfirmDialog } from '@/features/admin/components/AdminDataToolbar';
import { DashboardOverviewView } from '@/features/admin/components/DashboardOverviewView';
import { ProductsAdminView } from '@/features/admin/components/CatalogModulesView';
import { UsersAndPermissionsAdminView } from '@/features/admin/components/ReportsSettingsUsersAdminView';

function assertCondition(condition: unknown, message: string): void {
  if (!condition) {
    throw new Error(`Assertion failed: ${message}`);
  }
}

const TEST_PRODUCT: CatalogProduct = {
  id: 101,
  name: 'Heavyweight Boxy Tee',
  slug: 'heavyweight-boxy-tee',
  description: '280 GSM organic cotton jersey.',
  body: '100% GOTS-certified organic ring-spun cotton.',
  status: 'published',
  is_featured: true,
  brand: { id: 1, name: 'Atelier Véra', slug: 'atelier-vera' },
  category: { id: 2, name: 'T-Shirts', slug: 't-shirts' },
  images: [
    {
      id: 10,
      url: 'https://cdn.example.com/tee-front.jpg',
      alt_text: 'Front view of Heavyweight Boxy Tee',
      sort_order: 1,
      is_primary: true,
    },
    {
      id: 11,
      url: 'https://cdn.example.com/tee-back.jpg',
      alt_text: 'Back view of Heavyweight Boxy Tee',
      sort_order: 2,
      is_primary: false,
    },
  ],
  variants: [
    {
      id: 501,
      sku: 'TEE-BLK-M',
      color: 'Black',
      size: 'M',
      price: { amount: 185000, currency: 'BDT', display: 1850 },
      compare_at_price: { amount: 220000, currency: 'BDT', display: 2200 },
      status: 'active',
    },
    {
      id: 502,
      sku: 'TEE-WHT-L',
      color: 'White',
      size: 'L',
      price: { amount: 195000, currency: 'BDT', display: 1950 },
      compare_at_price: null,
      status: 'active',
    },
  ],
};

export async function runStorefrontTestSuite(): Promise<{ passed: number; names: string[] }> {
  const names: string[] = [];

  // 1. Money formatting & Canonical URLs
  {
    const formattedBdt = formatMoney({ amount: 185000, currency: 'BDT', display: 1850 });
    assertCondition(formattedBdt === '৳1,850.00', `Expected ৳1,850.00, got ${formattedBdt}`);

    const canonicalRoot = buildCanonicalUrl('/products', {
      page: 1,
      category: 't-shirts',
      color: '',
    });
    assertCondition(
      canonicalRoot.endsWith('/products?category=t-shirts'),
      `Expected canonical URL to strip page=1 and empty color, got ${canonicalRoot}`
    );
    names.push('SEO canonical URL builder & Money display formatting');
  }

  // 2. Schema.org Product JSON-LD structured data
  {
    const jsonLd = buildProductJsonLd(
      TEST_PRODUCT,
      'http://localhost:3000/products/heavyweight-boxy-tee'
    );
    assertCondition(jsonLd['@context'] === 'https://schema.org', 'JSON-LD @context mismatch');
    assertCondition(jsonLd['@type'] === 'Product', 'JSON-LD @type mismatch');
    assertCondition(jsonLd.name === 'Heavyweight Boxy Tee', 'JSON-LD product name mismatch');
    const offers = jsonLd.offers as { '@type': string; lowPrice: string; highPrice: string };
    assertCondition(
      offers['@type'] === 'AggregateOffer',
      'Multi-variant product must produce AggregateOffer'
    );
    assertCondition(offers.lowPrice === '1850.00', `Expected lowPrice 1850.00, got ${offers.lowPrice}`);
    assertCondition(offers.highPrice === '1950.00', `Expected highPrice 1950.00, got ${offers.highPrice}`);
    names.push('Product JSON-LD structured data generation');
  }

  // 3. ProductCard component rendering
  {
    const html = renderToStaticMarkup(<ProductCard product={TEST_PRODUCT} />);
    assertCondition(html.includes('Heavyweight Boxy Tee'), 'ProductCard must render product name');
    assertCondition(
      html.includes('/products/heavyweight-boxy-tee'),
      'ProductCard must link to product slug'
    );
    assertCondition(html.includes('৳1,850.00'), 'ProductCard must render lowest variant price');
    assertCondition(html.includes('৳2,200.00'), 'ProductCard must render compare-at price');
    assertCondition(html.includes('Featured'), 'ProductCard must render Featured badge');
    names.push('ProductCard Server Component rendering');
  }

  // 4. ProductImageGallery component rendering
  {
    const html = renderToStaticMarkup(
      <ProductImageGallery
        images={TEST_PRODUCT.images ?? []}
        productName={TEST_PRODUCT.name}
      />
    );
    assertCondition(
      html.includes('https://cdn.example.com/tee-front.jpg'),
      'Gallery must render primary image'
    );
    assertCondition(
      html.includes('View image 1 of 2') && html.includes('View image 2 of 2'),
      'Gallery must render accessible thumbnail tabs'
    );
    assertCondition(html.includes('1 / 2'), 'Gallery must render image counter');
    names.push('ProductImageGallery accessible rendering');
  }

  // 5. ProductVariantSelector component rendering
  {
    const html = renderToStaticMarkup(
      <StoreProviders>
        <ProductVariantSelector product={TEST_PRODUCT} />
      </StoreProviders>
    );
    assertCondition(html.includes('SKU: TEE-BLK-M'), 'Variant selector must show initial SKU');
    assertCondition(html.includes('৳1,850.00'), 'Variant selector must show authoritative price');
    assertCondition(
      html.includes('Color Black') && html.includes('Color White'),
      'Variant selector must render color options'
    );
    assertCondition(
      html.includes('Size M') && html.includes('Size L'),
      'Variant selector must render size options'
    );
    assertCondition(html.includes('Add to Bag'), 'Variant selector must render Add to Bag CTA');
    names.push('ProductVariantSelector color/size & price rendering');
  }

  // 6. OrderHistoryView, EmptyState, ErrorState & PaginationNav rendering
  {
    const emptyOrdersHtml = renderToStaticMarkup(<OrderHistoryView initialOrders={[]} />);
    assertCondition(
      emptyOrdersHtml.includes('No orders placed yet'),
      'OrderHistoryView must render empty state when orders list is empty'
    );

    const sampleOrders: Order[] = [
      {
        id: 'ord-1',
        orderNumber: 'AV-2026-0091',
        customerId: 'cust-1',
        status: 'paid',
        items: [
          {
            id: 'oi-1',
            orderId: 'ord-1',
            variantId: '501',
            sku: 'TEE-BLK-M',
            productName: 'Heavyweight Boxy Tee',
            size: 'M',
            color: 'Black',
            quantity: 2,
            unitPrice: { amount: 185000, currency: 'BDT', formatted: '৳1,850.00' },
            totalPrice: { amount: 370000, currency: 'BDT', formatted: '৳3,700.00' },
          },
        ],
        shippingAddress: {
          fullName: 'Nadia Karim',
          addressLine1: 'House 22, Road 7',
          city: 'Dhaka',
          state: 'Dhaka',
          postalCode: '1212',
          country: 'BD',
          phone: '+8801711000000',
        },
        total: { amount: 388500, currency: 'BDT', formatted: '৳3,885.00' },
        createdAt: '2026-11-09T10:00:00Z',
        updatedAt: '2026-11-09T10:00:00Z',
      },
    ];

    const populatedOrdersHtml = renderToStaticMarkup(
      <OrderHistoryView initialOrders={sampleOrders} />
    );
    assertCondition(
      populatedOrdersHtml.includes('Order #AV-2026-0091') &&
        populatedOrdersHtml.includes('৳3,885.00'),
      'OrderHistoryView must render order number and authoritative total'
    );

    const errHtml = renderToStaticMarkup(<ErrorState message="Backend timeout" />);
    assertCondition(
      errHtml.includes('role="alert"') && errHtml.includes('Backend timeout'),
      'ErrorState must render alert role and message'
    );

    const emptyHtml = renderToStaticMarkup(
      <EmptyState
        title="Nothing found"
        description="Try another filter"
        actionLabel="Reset"
        actionHref="/products"
      />
    );
    assertCondition(emptyHtml.includes('Nothing found'), 'EmptyState must render title');

    const navHtml = renderToStaticMarkup(
      <PaginationNav
        meta={{ current_page: 2, last_page: 4, per_page: 12, total: 40 }}
        basePath="/products"
        searchParams={{ category: 't-shirts' }}
      />
    );
    assertCondition(
      navHtml.includes('aria-label="Pagination"') &&
        navHtml.includes('/products?category=t-shirts') &&
        navHtml.includes('/products?category=t-shirts&amp;page=3'),
      'PaginationNav must build previous and next links preserving filters'
    );
    names.push('OrderHistoryView, EmptyState, ErrorState & PaginationNav rendering');
  }

  // 7. API Client Caching & Customer Privacy Guardrails
  {
    const capturedCalls: Array<{ url: string; init: RequestInit & { next?: unknown } }> = [];
    const originalFetch = globalThis.fetch;

    globalThis.fetch = (async (input: RequestInfo | URL, init?: RequestInit) => {
      capturedCalls.push({
        url: String(input),
        init: (init ?? {}) as RequestInit & { next?: unknown },
      });
      return new Response(JSON.stringify({ data: [] }), {
        status: 200,
        headers: { 'content-type': 'application/json' },
      });
    }) as typeof fetch;

    try {
      // Public catalog call -> uses next.tags for cache invalidation
      await apiRequest('/catalog/products', { tags: ['catalog', 'products'], revalidate: 60 });
      const publicCall = capturedCalls[0];
      assertCondition(
        publicCall &&
          publicCall.init.cache === undefined &&
          Boolean(publicCall.init.next),
        'Public catalog GET must attach Next.js revalidate & tags config'
      );

      // Private customer account call -> strictly enforces cache: no-store
      await apiRequest('/account/profile', { tags: ['should-be-ignored'] });
      const privateCall = capturedCalls[1];
      assertCondition(
        privateCall &&
          privateCall.init.cache === 'no-store' &&
          privateCall.init.next === undefined,
        'Customer /account endpoint must enforce cache: no-store and never use public cache tags'
      );

      // Checkout quote POST call -> strictly enforces cache: no-store
      await apiRequest('/checkout/quote', {
        method: 'POST',
        body: { items: [{ variant_id: 501, quantity: 1 }] },
      });
      const quoteCall = capturedCalls[2];
      assertCondition(
        quoteCall && quoteCall.init.cache === 'no-store',
        'Checkout quote endpoint must never be cached'
      );
    } finally {
      globalThis.fetch = originalFetch;
    }

    names.push('API client public tag caching vs private no-store customer data isolation');
  }

  // 8. Orders & payments integration with the Laravel contract
  {
    const baseOrder: Order = {
      id: 'ord-9',
      orderNumber: 'ORD-20261109-ABC123',
      customerId: '7',
      status: 'confirmed',
      items: [
        {
          id: 'oi-9',
          orderId: 'ord-9',
          variantId: '501',
          sku: 'TEE-BLK-M',
          productName: 'Heavyweight Boxy Tee',
          size: 'M',
          color: 'Black',
          quantity: 1,
          unitPrice: { amount: 185000, currency: 'BDT', formatted: '৳1,850.00' },
          totalPrice: { amount: 185000, currency: 'BDT', formatted: '৳1,850.00' },
        },
      ],
      shippingAddress: {
        fullName: 'Nadia Karim',
        addressLine1: 'House 22, Road 7',
        city: 'Dhaka',
        state: 'Dhaka',
        postalCode: '1212',
        country: 'BD',
        phone: '01711000000',
      },
      total: { amount: 185000, currency: 'BDT', formatted: '৳1,850.00' },
      createdAt: '2026-11-09T10:00:00Z',
      updatedAt: '2026-11-09T10:00:00Z',
    };

    // The API upper-cases `status`; the client must normalise it.
    const normalized = normalizeOrder({ ...baseOrder, status: 'CONFIRMED' as unknown as Order['status'] });
    assertCondition(normalized.status === 'confirmed', 'normalizeOrder must lower-case API status');

    assertCondition(
      isCancellableByCustomer({ status: 'pending_payment' }) &&
        isCancellableByCustomer({ status: 'confirmed' }) &&
        !isCancellableByCustomer({ status: 'processing' }) &&
        !isCancellableByCustomer({ status: 'shipped' }) &&
        !isCancellableByCustomer({ status: 'cancelled' }),
      'Customer cancellation must mirror backend cancellable statuses'
    );

    const confirmedHtml = renderToStaticMarkup(
      <OrderHistoryView
        initialOrders={[{ ...baseOrder, payment_status: 'cod_pending' }]}
      />
    );
    assertCondition(
      confirmedHtml.includes('CONFIRMED') && confirmedHtml.includes('PAYMENT: COD PENDING'),
      'Order history must show separate order and payment status badges'
    );
    assertCondition(
      confirmedHtml.includes('Cancel order'),
      'A confirmed order must offer a cancel action'
    );

    const shippedHtml = renderToStaticMarkup(
      <OrderHistoryView initialOrders={[{ ...baseOrder, status: 'shipped' }]} />
    );
    assertCondition(
      !shippedHtml.includes('Cancel order'),
      'A shipped order must not offer a cancel action'
    );

    const calls: Array<{ url: string; init: RequestInit }> = [];
    const originalFetch = globalThis.fetch;
    let mode: 'orders-500' | 'orders-ok' | 'pay' = 'orders-500';

    globalThis.fetch = (async (input: RequestInfo | URL, init?: RequestInit) => {
      calls.push({ url: String(input), init: init ?? {} });
      const json = (body: unknown, status: number) =>
        new Response(JSON.stringify(body), {
          status,
          headers: { 'content-type': 'application/json' },
        });

      if (mode === 'orders-500') return json({ message: 'Server exploded' }, 500);
      if (mode === 'orders-ok') {
        return json({ data: [{ ...baseOrder, status: 'CONFIRMED' }] }, 200);
      }
      return json(
        {
          data: {
            transaction: { status: 'pending', merchant_reference: 'TXN-ORD-1-AB12' },
            redirect_url: 'https://sandbox.payment.bkash.com/redirect/TR001',
          },
          idempotent_replay: false,
        },
        201
      );
    }) as typeof fetch;

    try {
      // Errors must surface, not turn into a misleading "No orders placed yet".
      let surfaced = false;
      try {
        await fetchCustomerOrders();
      } catch (err) {
        surfaced = err instanceof Error && err.message.includes('Server exploded');
      }
      assertCondition(surfaced, 'fetchCustomerOrders must throw API failures instead of returning []');

      mode = 'orders-ok';
      const orders = await fetchCustomerOrders();
      assertCondition(
        orders[0]?.status === 'confirmed' &&
          calls[calls.length - 1]?.url.endsWith('/orders') &&
          calls[calls.length - 1]?.init.cache === 'no-store',
        'Order history must call /orders uncached and normalise status'
      );

      mode = 'pay';
      const payment = await initiatePayment('ORD-20261109-ABC123', 'bkash', 'pay-key-1');
      const payCall = calls[calls.length - 1];
      const payBody = String(payCall?.init.body ?? '');
      const payHeaders = (payCall?.init.headers ?? {}) as Record<string, string>;
      assertCondition(
        payment.redirectUrl === 'https://sandbox.payment.bkash.com/redirect/TR001' &&
          payCall?.url.endsWith('/payments/initiate') &&
          payCall?.init.cache === 'no-store',
        'initiatePayment must hit /payments/initiate uncached and return the redirect URL'
      );
      assertCondition(
        payHeaders['Idempotency-Key'] === 'pay-key-1',
        'initiatePayment must send an Idempotency-Key header'
      );
      assertCondition(
        !/amount|price|total/i.test(payBody) && payBody.includes('ORD-20261109-ABC123'),
        'initiatePayment must never send a price from the browser'
      );
      assertCondition(
        isOnlinePaymentMethod('bkash') && isOnlinePaymentMethod('nagad') &&
          isOnlinePaymentMethod('sslcommerz') && !isOnlinePaymentMethod('cod'),
        'Only gateway methods are treated as online payments'
      );
    } finally {
      globalThis.fetch = originalFetch;
    }

    names.push('Orders & payments contract: status normalisation, cancel rules, surfaced errors, no client prices');
  }

  // 9. Centralized Admin Dashboard: 14 modules, permission-aware menu, backend metrics, confirmation dialogs & audit trail
  {
    assertCondition(
      ADMIN_NAV_ITEMS.length === 14,
      'ADMIN_NAV_ITEMS must define all 14 centralized Admin Dashboard modules'
    );

    const superAdmin: AdminStaffUser = {
      id: 1,
      name: 'Super Administrator',
      email: 'admin@clothing-commerce.test',
      role: 'super_admin',
      status: 'active',
    };

    const supportStaff: AdminStaffUser = {
      id: 2,
      name: 'Support Agent',
      email: 'support@clothing-commerce.test',
      role: 'customer_support',
      status: 'active',
      permissions: ['orders.view', 'customers.view', 'reviews.view'],
    };

    assertCondition(
      getPermittedNavItems(superAdmin).length === 14,
      'super_admin must have access to all 14 sidebar navigation modules'
    );

    const supportNavKeys = getPermittedNavItems(supportStaff).map((i) => i.key);
    assertCondition(
      supportNavKeys.includes('orders') &&
        supportNavKeys.includes('customers') &&
        supportNavKeys.includes('reviews') &&
        !supportNavKeys.includes('products') &&
        !supportNavKeys.includes('settings') &&
        !supportNavKeys.includes('users'),
      'Permission-aware menu must filter out unauthorized modules for customer_support'
    );

    assertCondition(
      userHasPermission(superAdmin, 'settings.manage') &&
        !userHasPermission(supportStaff, 'settings.manage'),
      'userHasPermission must enforce granular RBAC permissions'
    );

    // Unauthorized access to Settings module renders 403 Access Restricted state
    const forbiddenShellHtml = renderToStaticMarkup(
      <AdminAuthProvider initialUser={supportStaff}>
        <AdminShell requiredPermissions={['settings.view']} title="Settings">
          <div>Secret Settings Content</div>
        </AdminShell>
      </AdminAuthProvider>
    );
    assertCondition(
      forbiddenShellHtml.includes('403 Forbidden') &&
        forbiddenShellHtml.includes('Access Restricted') &&
        !forbiddenShellHtml.includes('Secret Settings Content'),
      'AdminShell must block rendering when staff member lacks required module permission'
    );

    // Dashboard metrics view renders backend API metrics (Total Sales, Revenue, AOV, Orders Today, Pending, Delivered, Low-Stock, Best-Sellers)
    const sampleMetrics: AdminDashboardMetrics = {
      currency: 'BDT',
      total_sales: 967000,
      total_sales_count: 2,
      orders_today: 2,
      pending_orders: 1,
      delivered_orders: 1,
      revenue: 640000,
      average_order_value: 483500,
      low_stock_count: 1,
      low_stock_products: [
        {
          inventory_item_id: 11,
          product_id: 101,
          product_name: 'Embroidered Cotton Panjabi',
          product_variant_id: 501,
          sku: 'PNJ-BLK-L',
          color: 'Black',
          size: 'L',
          warehouse_id: 1,
          warehouse_name: 'Dhaka Central Hub',
          warehouse_code: 'DHK',
          quantity_on_hand: 3,
          quantity_reserved: 0,
          available_quantity: 3,
          low_stock_threshold: 5,
          is_out_of_stock: false,
        },
      ],
      best_selling_products: [
        {
          product_id: 101,
          product_name: 'Embroidered Cotton Panjabi',
          sku: 'PNJ-BLK-L',
          total_quantity_sold: 3,
          total_revenue: 960000,
          orders_count: 2,
          currency: 'BDT',
        },
      ],
      status_breakdown: [{ status: 'delivered', count: 1, total_amount: 640000 }],
      recent_orders: [
        {
          id: 1,
          order_number: 'ORD-20261115-AAA111',
          customer_name: 'Nadia Rahman',
          status: 'delivered',
          payment_status: 'paid',
          fulfilment_status: 'delivered',
          payment_method: 'bkash',
          total_amount: 640000,
          currency: 'BDT',
        },
      ],
    };

    const dashHtml = renderToStaticMarkup(
      <AdminAuthProvider initialUser={superAdmin}>
        <DashboardOverviewView initialMetrics={sampleMetrics} />
      </AdminAuthProvider>
    );
    assertCondition(
      dashHtml.includes('Total Sales') &&
        dashHtml.includes('৳9,670.00') &&
        dashHtml.includes('Revenue (Paid / Settled)') &&
        dashHtml.includes('৳6,400.00') &&
        dashHtml.includes('Average Order Value') &&
        dashHtml.includes('৳4,835.00') &&
        dashHtml.includes('Low-Stock Products') &&
        dashHtml.includes('Best-Selling Products') &&
        dashHtml.includes('Embroidered Cotton Panjabi') &&
        dashHtml.includes('ORD-20261115-AAA111'),
      'DashboardOverviewView must render all 8 required backend KPIs and product tables'
    );

    // Confirmation Dialog & Searchable Data Table with Export & Audit History
    const dialogHtml = renderToStaticMarkup(
      <ConfirmDialog
        open={true}
        title="Delete Product?"
        description="This action is logged in the Audit Trail."
        variant="destructive"
        requireReason={true}
        onConfirm={() => {}}
        onCancel={() => {}}
      />
    );
    assertCondition(
      dialogHtml.includes('role="alertdialog"') &&
        dialogHtml.includes('Delete Product?'),
      'ConfirmDialog must render accessible alertdialog modal'
    );

    const productsAdminHtml = renderToStaticMarkup(
      <AdminAuthProvider initialUser={superAdmin}>
        <ProductsAdminView initialProducts={[TEST_PRODUCT]} />
      </AdminAuthProvider>
    );
    assertCondition(
      productsAdminHtml.includes('Heavyweight Boxy Tee') &&
        productsAdminHtml.includes('Export CSV') &&
        productsAdminHtml.includes('+ New Product'),
      'ProductsAdminView must render searchable table, CSV export, and management actions'
    );

    const governanceHtml = renderToStaticMarkup(
      <AdminAuthProvider initialUser={superAdmin}>
        <UsersAndPermissionsAdminView
          initialUsers={[superAdmin, supportStaff]}
          initialRoles={[{ id: 1, name: 'super_admin', permissions: ['users.manage'] }]}
          initialAuditLogs={[
            {
              id: 99,
              user_email: 'admin@clothing-commerce.test',
              action: 'settings.updated',
              created_at: '2026-11-15T01:00:00Z',
            },
          ]}
        />
      </AdminAuthProvider>
    );
    assertCondition(
      governanceHtml.includes('Staff &amp; User Accounts') &&
        governanceHtml.includes('Roles &amp; Permissions Matrix') &&
        governanceHtml.includes('Audit History'),
      'UsersAndPermissionsAdminView must render Staff Users, Roles & Permissions, and Audit History tabs'
    );

    names.push(
      'Centralized Admin Dashboard: 14 modules, permission-aware sidebar, backend metrics, confirmation dialogs & audit history'
    );
  }

  return { passed: names.length, names };
}

