<?php

namespace App\Modules\Reporting\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Reviews\Models\ProductReview;
use App\Modules\Shipping\Models\Shipment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportingService
{
    /**
     * Compute authoritative real-time Dashboard metrics from database records.
     * All monetary values are integer paisa (BDT subunits).
     */
    public function getDashboardMetrics(array $filters = []): array
    {
        $ordersQuery = Order::query();

        if (!empty($filters['from_date'])) {
            $ordersQuery->whereDate('created_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $ordersQuery->whereDate('created_at', '<=', $filters['to_date']);
        }

        // Valid (non-cancelled) orders for Gross Sales & Average Order Value
        $validOrdersQuery = (clone $ordersQuery)->whereNotIn('status', ['cancelled']);
        $totalSalesCount = (clone $validOrdersQuery)->count();
        $totalSalesAmount = (int) (clone $validOrdersQuery)->sum('total_amount');

        // Revenue from paid or delivered orders (excluding cancelled/refunded)
        $revenue = (int) (clone $ordersQuery)
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->where(function ($q) {
                $q->where('payment_status', 'paid')
                  ->orWhere('status', 'delivered');
            })
            ->sum('total_amount');

        $averageOrderValue = $totalSalesCount > 0
            ? (int) round($totalSalesAmount / $totalSalesCount)
            : 0;

        $todayStart = Carbon::today();
        $ordersToday = Order::query()
            ->where('created_at', '>=', $todayStart)
            ->count();

        $pendingOrders = (clone $ordersQuery)
            ->whereIn('status', ['pending', 'payment_pending', 'confirmed', 'processing'])
            ->count();

        $deliveredOrders = (clone $ordersQuery)
            ->where('status', 'delivered')
            ->count();

        $lowStockProducts = $this->getLowStockProducts(10);
        $lowStockCount = InventoryItem::query()
            ->whereHas('warehouse', fn ($q) => $q->where('is_active', true))
            ->whereRaw('(quantity_on_hand - quantity_reserved) <= low_stock_threshold')
            ->count();

        $bestSellingProducts = $this->getBestSellingProducts(
            limit: 10,
            fromDate: $filters['from_date'] ?? null,
            toDate: $filters['to_date'] ?? null
        );

        $statusBreakdown = Order::query()
            ->selectRaw('status, COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total_amount')
            ->groupBy('status')
            ->get()
            ->map(fn ($row) => [
                'status'       => (string) $row->status,
                'count'        => (int) $row->count,
                'total_amount' => (int) $row->total_amount,
            ])
            ->values()
            ->all();

        $recentOrders = Order::query()
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(fn (Order $o) => [
                'id'                => $o->id,
                'order_number'      => $o->order_number,
                'customer_name'     => $o->shipping_address_snapshot['recipient_name'] ?? 'Customer',
                'status'            => $o->status,
                'payment_status'    => $o->payment_status,
                'fulfilment_status' => $o->fulfilment_status,
                'payment_method'    => $o->payment_method,
                'total_amount'      => (int) $o->total_amount,
                'currency'          => $o->currency ?? 'BDT',
                'placed_at'         => $o->placed_at?->toIso8601String() ?? $o->created_at?->toIso8601String(),
            ])
            ->all();

        return [
            'currency'              => 'BDT',
            'total_sales'           => $totalSalesAmount,
            'total_sales_count'     => $totalSalesCount,
            'orders_today'          => $ordersToday,
            'pending_orders'        => $pendingOrders,
            'delivered_orders'      => $deliveredOrders,
            'revenue'               => $revenue,
            'average_order_value'   => $averageOrderValue,
            'low_stock_count'       => $lowStockCount,
            'low_stock_products'    => $lowStockProducts,
            'best_selling_products' => $bestSellingProducts,
            'status_breakdown'      => $statusBreakdown,
            'recent_orders'         => $recentOrders,
            'counts'                => [
                'total_products'   => Product::query()->count(),
                'total_customers'  => User::query()->where('role', 'customer')->count(),
                'pending_reviews'  => ProductReview::query()->where('status', 'pending')->count(),
                'active_shipments' => Shipment::query()->whereIn('status', ['created', 'picked_up', 'in_transit', 'out_for_delivery'])->count(),
            ],
        ];
    }

    /**
     * Get low-stock products across active warehouses.
     */
    public function getLowStockProducts(int $limit = 20): array
    {
        return InventoryItem::query()
            ->with(['productVariant.product', 'warehouse'])
            ->whereHas('warehouse', fn ($q) => $q->where('is_active', true))
            ->whereRaw('(quantity_on_hand - quantity_reserved) <= low_stock_threshold')
            ->orderByRaw('(quantity_on_hand - quantity_reserved) ASC')
            ->limit($limit)
            ->get()
            ->map(function (InventoryItem $item) {
                $onHand = (int) $item->quantity_on_hand;
                $reserved = (int) $item->quantity_reserved;
                $available = max(0, $onHand - $reserved);

                return [
                    'inventory_item_id'   => $item->id,
                    'product_id'          => $item->productVariant?->product_id,
                    'product_name'        => $item->productVariant?->product?->name ?? 'Unknown Product',
                    'product_slug'        => $item->productVariant?->product?->slug,
                    'product_variant_id'  => $item->product_variant_id,
                    'sku'                 => $item->productVariant?->sku ?? 'N/A',
                    'color'               => $item->productVariant?->color,
                    'size'                => $item->productVariant?->size,
                    'warehouse_id'        => $item->warehouse_id,
                    'warehouse_name'      => $item->warehouse?->name ?? 'Default Warehouse',
                    'warehouse_code'      => $item->warehouse?->code ?? 'WH',
                    'quantity_on_hand'    => $onHand,
                    'quantity_reserved'   => $reserved,
                    'available_quantity'  => $available,
                    'low_stock_threshold' => (int) $item->low_stock_threshold,
                    'is_out_of_stock'     => $available <= 0,
                ];
            })
            ->all();
    }

    /**
     * Get best-selling products aggregated from order_items of non-cancelled orders.
     */
    public function getBestSellingProducts(
        int $limit = 20,
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {
        $query = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotIn('orders.status', ['cancelled']);

        if ($fromDate) {
            $query->whereDate('orders.created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate('orders.created_at', '<=', $toDate);
        }

        return $query
            ->selectRaw('
                order_items.product_id,
                MAX(order_items.product_name_snapshot) as product_name,
                MAX(order_items.sku_snapshot) as sample_sku,
                SUM(order_items.quantity) as total_quantity_sold,
                SUM(order_items.line_total_amount) as total_revenue,
                COUNT(DISTINCT order_items.order_id) as orders_count
            ')
            ->groupBy('order_items.product_id')
            ->orderByDesc('total_quantity_sold')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'product_id'          => $row->product_id !== null ? (int) $row->product_id : null,
                'product_name'        => (string) $row->product_name,
                'sku'                 => (string) $row->sample_sku,
                'total_quantity_sold' => (int) $row->total_quantity_sold,
                'total_revenue'       => (int) $row->total_revenue,
                'orders_count'        => (int) $row->orders_count,
                'currency'            => 'BDT',
            ])
            ->all();
    }

    /**
     * Get comprehensive reports overview (sales series, payment breakdown, top products, inventory health).
     */
    public function getComprehensiveReport(array $filters = []): array
    {
        $metrics = $this->getDashboardMetrics($filters);

        $ordersQuery = Order::query()->whereNotIn('status', ['cancelled']);
        if (!empty($filters['from_date'])) {
            $ordersQuery->whereDate('created_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $ordersQuery->whereDate('created_at', '<=', $filters['to_date']);
        }

        $paymentMethodBreakdown = (clone $ordersQuery)
            ->selectRaw('payment_method, COUNT(*) as orders_count, COALESCE(SUM(total_amount), 0) as total_amount')
            ->groupBy('payment_method')
            ->get()
            ->map(fn ($row) => [
                'payment_method' => (string) $row->payment_method,
                'orders_count'   => (int) $row->orders_count,
                'total_amount'   => (int) $row->total_amount,
            ])
            ->values()
            ->all();

        $dailySales = (clone $ordersQuery)
            ->selectRaw('DATE(created_at) as sale_date, COUNT(*) as orders_count, COALESCE(SUM(total_amount), 0) as total_sales, COALESCE(SUM(CASE WHEN payment_status = ? OR status = ? THEN total_amount ELSE 0 END), 0) as revenue', ['paid', 'delivered'])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy(DB::raw('DATE(created_at)'), 'desc')
            ->limit(30)
            ->get()
            ->map(fn ($row) => [
                'date'         => (string) $row->sale_date,
                'orders_count' => (int) $row->orders_count,
                'total_sales'  => (int) $row->total_sales,
                'revenue'      => (int) $row->revenue,
            ])
            ->values()
            ->all();

        return [
            'metrics'                  => $metrics,
            'payment_method_breakdown' => $paymentMethodBreakdown,
            'daily_sales'              => $dailySales,
        ];
    }
}

