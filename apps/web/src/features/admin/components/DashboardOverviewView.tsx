'use client';

import * as React from 'react';
import Link from 'next/link';
import type { AdminDashboardMetrics } from '@clothing-commerce/types';
import {
  downloadAdminCsvExport,
  fetchAdminDashboardMetrics,
} from '@/features/admin/api/adminApi';
import { formatPrice } from '@/lib/utils/format';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export interface DashboardOverviewViewProps {
  initialMetrics?: AdminDashboardMetrics | null;
}

export function DashboardOverviewView({
  initialMetrics = null,
}: DashboardOverviewViewProps) {
  const [metrics, setMetrics] = React.useState<AdminDashboardMetrics | null>(
    initialMetrics
  );
  const [isLoading, setIsLoading] = React.useState<boolean>(initialMetrics === null);
  const [error, setError] = React.useState<string | null>(null);
  const [fromDate, setFromDate] = React.useState<string>('');
  const [toDate, setToDate] = React.useState<string>('');
  const [isExporting, setIsExporting] = React.useState<boolean>(false);

  const loadMetrics = React.useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const response = await fetchAdminDashboardMetrics({
        from_date: fromDate || undefined,
        to_date: toDate || undefined,
      });
      setMetrics(response.data);
    } catch (err) {
      setError(
        err instanceof Error ? err.message : 'Failed to load dashboard metrics from backend API.'
      );
    } finally {
      setIsLoading(false);
    }
  }, [fromDate, toDate]);

  React.useEffect(() => {
    if (initialMetrics === null) {
      void loadMetrics();
    }
  }, [initialMetrics, loadMetrics]);

  const handleExport = async () => {
    setIsExporting(true);
    try {
      await downloadAdminCsvExport('reports-sales');
    } finally {
      setIsExporting(false);
    }
  };

  if (isLoading && !metrics) {
    return (
      <div className="space-y-4">
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {Array.from({ length: 6 }).map((_, idx) => (
            <div
              key={idx}
              className="h-28 animate-pulse rounded-xl border border-neutral-200 bg-white p-5"
            />
          ))}
        </div>
      </div>
    );
  }

  if (error && !metrics) {
    return (
      <div
        role="alert"
        className="rounded-xl border border-red-200 bg-red-50 p-6 text-sm text-red-800"
      >
        <p className="font-semibold">Unable to fetch live dashboard analytics</p>
        <p className="mt-1 text-xs">{error}</p>
        <Button
          type="button"
          variant="outline"
          size="sm"
          className="mt-3"
          onClick={() => void loadMetrics()}
        >
          Retry
        </Button>
      </div>
    );
  }

  if (!metrics) {
    return null;
  }

  const currency = metrics.currency || 'BDT';

  const kpiCards = [
    {
      key: 'total_sales',
      label: 'Total Sales',
      value: formatPrice({ amount: metrics.total_sales, currency }),
      subtext: `${metrics.total_sales_count} valid orders`,
    },
    {
      key: 'revenue',
      label: 'Revenue (Paid / Settled)',
      value: formatPrice({ amount: metrics.revenue, currency }),
      subtext: 'Confirmed & settled payments',
    },
    {
      key: 'average_order_value',
      label: 'Average Order Value',
      value: formatPrice({ amount: metrics.average_order_value, currency }),
      subtext: 'Mean order value (BDT)',
    },
    {
      key: 'orders_today',
      label: 'Orders Today',
      value: String(metrics.orders_today),
      subtext: 'Placed since 00:00 today',
    },
    {
      key: 'pending_orders',
      label: 'Pending Orders',
      value: String(metrics.pending_orders),
      subtext: 'Awaiting confirmation or dispatch',
    },
    {
      key: 'delivered_orders',
      label: 'Delivered Orders',
      value: String(metrics.delivered_orders),
      subtext: 'Completed last-mile deliveries',
    },
  ];

  return (
    <div className="space-y-6">
      {/* Filter & Export Bar */}
      <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-neutral-200 bg-white p-4 shadow-2xs">
        <div className="flex flex-wrap items-center gap-3">
          <div className="flex items-center gap-2">
            <label htmlFor="dash-from-date" className="text-xs font-medium text-neutral-600">
              From:
            </label>
            <Input
              id="dash-from-date"
              type="date"
              value={fromDate}
              onChange={(e) => setFromDate(e.target.value)}
              className="h-9 w-40 text-xs"
            />
          </div>
          <div className="flex items-center gap-2">
            <label htmlFor="dash-to-date" className="text-xs font-medium text-neutral-600">
              To:
            </label>
            <Input
              id="dash-to-date"
              type="date"
              value={toDate}
              onChange={(e) => setToDate(e.target.value)}
              className="h-9 w-40 text-xs"
            />
          </div>
          <Button type="button" size="sm" onClick={() => void loadMetrics()}>
            Refresh Metrics
          </Button>
        </div>

        <div className="flex items-center gap-2">
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={() => void handleExport()}
            disabled={isExporting}
          >
            {isExporting ? 'Exporting...' : 'Export Sales CSV'}
          </Button>
        </div>
      </div>

      {/* Primary 6 KPI Cards */}
      <section aria-label="Core Commerce KPIs" className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {kpiCards.map((card) => (
          <div
            key={card.key}
            className="rounded-xl border border-neutral-200 bg-white p-5 shadow-2xs"
          >
            <p className="text-xs font-semibold uppercase tracking-wider text-neutral-500">
              {card.label}
            </p>
            <p className="mt-2 text-2xl font-bold tracking-tight text-neutral-900">
              {card.value}
            </p>
            <p className="mt-1 text-xs text-neutral-500">{card.subtext}</p>
          </div>
        ))}
      </section>

      {/* Low-Stock Products & Best-Selling Products Side-by-Side */}
      <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
        {/* Low-Stock Products */}
        <section
          aria-label="Low-Stock Products"
          className="rounded-xl border border-neutral-200 bg-white p-5 shadow-2xs"
        >
          <div className="flex items-center justify-between border-b border-neutral-100 pb-3">
            <div>
              <h2 className="text-sm font-bold text-neutral-900">
                Low-Stock Products
              </h2>
              <p className="text-xs text-neutral-500">
                Variants at or below safety threshold across active warehouses
              </p>
            </div>
            <Badge variant={metrics.low_stock_count > 0 ? 'destructive' : 'secondary'}>
              {metrics.low_stock_count} Alerts
            </Badge>
          </div>

          {metrics.low_stock_products.length === 0 ? (
            <p className="py-8 text-center text-xs text-neutral-500">
              All product variants have healthy stock levels.
            </p>
          ) : (
            <div className="mt-3 overflow-x-auto">
              <table className="w-full text-left text-xs">
                <thead>
                  <tr className="border-b border-neutral-200 text-[11px] uppercase tracking-wider text-neutral-500">
                    <th className="py-2 pr-3">Product / SKU</th>
                    <th className="px-3 py-2">Warehouse</th>
                    <th className="px-3 py-2 text-right">Available</th>
                    <th className="pl-3 py-2 text-right">Threshold</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-neutral-100">
                  {metrics.low_stock_products.map((item) => (
                    <tr key={item.inventory_item_id}>
                      <td className="py-2.5 pr-3">
                        <div className="font-semibold text-neutral-900">
                          {item.product_name}
                        </div>
                        <div className="text-[11px] text-neutral-500">
                          {item.sku}
                          {item.color ? ` · ${item.color}` : ''}
                          {item.size ? ` / ${item.size}` : ''}
                        </div>
                      </td>
                      <td className="px-3 py-2.5 text-neutral-600">
                        {item.warehouse_name}
                      </td>
                      <td className="px-3 py-2.5 text-right font-bold text-red-600">
                        {item.available_quantity}
                      </td>
                      <td className="pl-3 py-2.5 text-right text-neutral-500">
                        {item.low_stock_threshold}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          <div className="mt-4 border-t border-neutral-100 pt-3 text-right">
            <Link
              href="/admin/inventory"
              className="text-xs font-semibold text-neutral-900 hover:underline"
            >
              Manage Inventory & Restock →
            </Link>
          </div>
        </section>

        {/* Best-Selling Products */}
        <section
          aria-label="Best-Selling Products"
          className="rounded-xl border border-neutral-200 bg-white p-5 shadow-2xs"
        >
          <div className="flex items-center justify-between border-b border-neutral-100 pb-3">
            <div>
              <h2 className="text-sm font-bold text-neutral-900">
                Best-Selling Products
              </h2>
              <p className="text-xs text-neutral-500">
                Ranked by units sold and line-item revenue
              </p>
            </div>
            <Badge variant="secondary">Top Performers</Badge>
          </div>

          {metrics.best_selling_products.length === 0 ? (
            <p className="py-8 text-center text-xs text-neutral-500">
              No completed product sales recorded in this period.
            </p>
          ) : (
            <div className="mt-3 overflow-x-auto">
              <table className="w-full text-left text-xs">
                <thead>
                  <tr className="border-b border-neutral-200 text-[11px] uppercase tracking-wider text-neutral-500">
                    <th className="py-2 pr-3">Product</th>
                    <th className="px-3 py-2 text-right">Units Sold</th>
                    <th className="px-3 py-2 text-right">Orders</th>
                    <th className="pl-3 py-2 text-right">Revenue</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-neutral-100">
                  {metrics.best_selling_products.map((prod, idx) => (
                    <tr key={`${prod.product_id ?? idx}-${prod.sku}`}>
                      <td className="py-2.5 pr-3">
                        <div className="font-semibold text-neutral-900">
                          {prod.product_name}
                        </div>
                        <div className="text-[11px] text-neutral-500">
                          SKU: {prod.sku}
                        </div>
                      </td>
                      <td className="px-3 py-2.5 text-right font-semibold text-neutral-900">
                        {prod.total_quantity_sold}
                      </td>
                      <td className="px-3 py-2.5 text-right text-neutral-600">
                        {prod.orders_count}
                      </td>
                      <td className="pl-3 py-2.5 text-right font-semibold text-neutral-900">
                        {formatPrice({
                          amount: prod.total_revenue,
                          currency: prod.currency || currency,
                        })}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          <div className="mt-4 border-t border-neutral-100 pt-3 text-right">
            <Link
              href="/admin/reports"
              className="text-xs font-semibold text-neutral-900 hover:underline"
            >
              View Full Sales & Analytics Reports →
            </Link>
          </div>
        </section>
      </div>

      {/* Recent Orders Feed */}
      <section
        aria-label="Recent Orders"
        className="rounded-xl border border-neutral-200 bg-white p-5 shadow-2xs"
      >
        <div className="flex items-center justify-between border-b border-neutral-100 pb-3">
          <h2 className="text-sm font-bold text-neutral-900">Recent Orders</h2>
          <Link
            href="/admin/orders"
            className="text-xs font-semibold text-neutral-900 hover:underline"
          >
            All Orders →
          </Link>
        </div>

        {metrics.recent_orders.length === 0 ? (
          <p className="py-6 text-center text-xs text-neutral-500">
            No orders placed yet.
          </p>
        ) : (
          <div className="mt-3 overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead>
                <tr className="border-b border-neutral-200 text-[11px] uppercase tracking-wider text-neutral-500">
                  <th className="py-2 pr-3">Order #</th>
                  <th className="px-3 py-2">Customer</th>
                  <th className="px-3 py-2">Status</th>
                  <th className="px-3 py-2">Payment</th>
                  <th className="pl-3 py-2 text-right">Total</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {metrics.recent_orders.map((ord) => (
                  <tr key={ord.id}>
                    <td className="py-2.5 pr-3 font-mono font-semibold text-neutral-900">
                      {ord.order_number}
                    </td>
                    <td className="px-3 py-2.5 text-neutral-700">{ord.customer_name}</td>
                    <td className="px-3 py-2.5">
                      <Badge variant="outline">{ord.status}</Badge>
                    </td>
                    <td className="px-3 py-2.5">
                      <Badge variant="secondary">
                        {ord.payment_method.toUpperCase()} · {ord.payment_status}
                      </Badge>
                    </td>
                    <td className="pl-3 py-2.5 text-right font-semibold text-neutral-900">
                      {formatPrice({
                        amount: ord.total_amount,
                        currency: ord.currency || currency,
                      })}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>
    </div>
  );
}

