<?php

namespace App\Modules\Reporting\Controllers;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Identity\Models\AuditLog;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\AuditService;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Models\PaymentTransaction;
use App\Modules\Promotions\Models\Coupon;
use App\Modules\Reporting\Services\ReportingService;
use App\Modules\Reviews\Models\ProductReview;
use App\Modules\Shipping\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

class AdminExportController extends Controller
{
    public function __construct(
        protected ReportingService $reportingService,
        protected AuditService $auditService,
    ) {}

    /**
     * Resource-to-permission map so every export is protected by its domain permission.
     */
    private const RESOURCE_PERMISSIONS = [
        'orders'        => 'orders.view',
        'products'      => 'catalog.view',
        'categories'    => 'catalog.view',
        'variants'      => 'catalog.view',
        'inventory'     => 'inventory.view',
        'customers'     => 'customers.view',
        'payments'      => 'payments.view',
        'promotions'    => 'promotions.view',
        'shipments'     => 'shipping.view',
        'reviews'       => 'reviews.view',
        'audit-logs'    => 'audit.view',
        'reports-sales' => 'reports.view',
    ];

    /**
     * GET /api/admin/export/{resource}
     */
    public function export(Request $request, string $resource): Response
    {
        abort_unless(
            array_key_exists($resource, self::RESOURCE_PERMISSIONS),
            404,
            "Unsupported export resource '{$resource}'."
        );

        $requiredPermission = self::RESOURCE_PERMISSIONS[$resource];
        $user = $request->user('sanctum') ?? $request->user();

        abort_unless(
            $user !== null && ($user->can($requiredPermission) || $user->isSuperAdmin()),
            403,
            "You do not have permission ({$requiredPermission}) to export {$resource}."
        );

        [$headers, $rows] = $this->buildDataset($resource, $request);

        $this->auditService->log("export.{$resource}", $user, null, null, [
            'resource'  => $resource,
            'row_count' => count($rows),
        ]);

        $csv = $this->toCsvString($headers, $rows);
        $filename = sprintf('%s-export-%s.csv', $resource, now()->format('Ymd-His'));

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function buildDataset(string $resource, Request $request): array
    {
        return match ($resource) {
            'orders' => [
                ['Order Number', 'Status', 'Payment Status', 'Fulfilment Status', 'Payment Method', 'Total (Paisa)', 'Total (BDT)', 'Currency', 'Placed At'],
                Order::query()
                    ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
                    ->orderByDesc('id')
                    ->limit(1000)
                    ->get()
                    ->map(fn (Order $o) => [
                        $o->order_number,
                        $o->status,
                        $o->payment_status,
                        $o->fulfilment_status,
                        $o->payment_method,
                        (int) $o->total_amount,
                        number_format(((int) $o->total_amount) / 100, 2, '.', ''),
                        $o->currency ?? 'BDT',
                        $o->placed_at?->toIso8601String() ?? $o->created_at?->toIso8601String(),
                    ])->all(),
            ],
            'products' => [
                ['ID', 'Name', 'Slug', 'Category', 'Brand', 'Status', 'Featured', 'Variants Count', 'Created At'],
                Product::query()
                    ->with(['category:id,name', 'brand:id,name'])
                    ->withCount('variants')
                    ->orderByDesc('id')
                    ->limit(1000)
                    ->get()
                    ->map(fn (Product $p) => [
                        $p->id,
                        $p->name,
                        $p->slug,
                        $p->category?->name ?? '',
                        $p->brand?->name ?? '',
                        $p->status,
                        $p->is_featured ? 'Yes' : 'No',
                        (int) $p->variants_count,
                        $p->created_at?->toIso8601String(),
                    ])->all(),
            ],
            'categories' => [
                ['ID', 'Name', 'Slug', 'Parent ID', 'Active', 'Sort Order', 'Created At'],
                Category::query()
                    ->orderBy('sort_order')
                    ->limit(1000)
                    ->get()
                    ->map(fn (Category $c) => [
                        $c->id,
                        $c->name,
                        $c->slug,
                        $c->parent_id ?? '',
                        $c->is_active ? 'Yes' : 'No',
                        (int) $c->sort_order,
                        $c->created_at?->toIso8601String(),
                    ])->all(),
            ],
            'variants' => [
                ['ID', 'Product ID', 'Product Name', 'SKU', 'Color', 'Size', 'Price (Paisa)', 'Price (BDT)', 'Status'],
                ProductVariant::query()
                    ->with('product:id,name')
                    ->orderByDesc('id')
                    ->limit(1000)
                    ->get()
                    ->map(fn (ProductVariant $v) => [
                        $v->id,
                        $v->product_id,
                        $v->product?->name ?? '',
                        $v->sku,
                        $v->color ?? '',
                        $v->size ?? '',
                        (int) $v->price_amount,
                        number_format(((int) $v->price_amount) / 100, 2, '.', ''),
                        $v->status,
                    ])->all(),
            ],
            'inventory' => [
                ['ID', 'SKU', 'Product', 'Warehouse', 'On Hand', 'Reserved', 'Available', 'Low Stock Threshold'],
                InventoryItem::query()
                    ->with(['productVariant.product', 'warehouse'])
                    ->orderBy('id')
                    ->limit(1000)
                    ->get()
                    ->map(fn (InventoryItem $i) => [
                        $i->id,
                        $i->productVariant?->sku ?? '',
                        $i->productVariant?->product?->name ?? '',
                        $i->warehouse?->name ?? '',
                        (int) $i->quantity_on_hand,
                        (int) $i->quantity_reserved,
                        max(0, (int) $i->quantity_on_hand - (int) $i->quantity_reserved),
                        (int) $i->low_stock_threshold,
                    ])->all(),
            ],
            'customers' => [
                ['ID', 'Name', 'Email', 'Phone', 'Status', 'Created At'],
                User::query()
                    ->where('role', 'customer')
                    ->orderByDesc('id')
                    ->limit(1000)
                    ->get()
                    ->map(fn (User $u) => [
                        $u->id,
                        $u->name,
                        $u->email,
                        $u->phone ?? '',
                        $u->status,
                        $u->created_at?->toIso8601String(),
                    ])->all(),
            ],
            'payments' => [
                ['ID', 'Order Number', 'Merchant Reference', 'Gateway', 'Gateway Tx ID', 'Amount (Paisa)', 'Refunded (Paisa)', 'Status', 'Paid At'],
                PaymentTransaction::query()
                    ->with('order:id,order_number')
                    ->orderByDesc('id')
                    ->limit(1000)
                    ->get()
                    ->map(fn (PaymentTransaction $t) => [
                        $t->id,
                        $t->order?->order_number ?? '',
                        $t->merchant_reference,
                        $t->gateway,
                        $t->gateway_transaction_id ?? '',
                        (int) $t->amount,
                        (int) $t->refunded_amount,
                        $t->status,
                        $t->paid_at?->toIso8601String() ?? '',
                    ])->all(),
            ],
            'promotions' => [
                ['ID', 'Code', 'Type', 'Value', 'Min Order (Paisa)', 'Used Count', 'Active', 'Expires At'],
                Coupon::query()
                    ->orderByDesc('id')
                    ->limit(1000)
                    ->get()
                    ->map(fn (Coupon $c) => [
                        $c->id,
                        $c->code,
                        $c->type,
                        (int) $c->value,
                        (int) $c->min_order_amount,
                        (int) $c->used_count,
                        $c->is_active ? 'Yes' : 'No',
                        $c->expires_at?->toIso8601String() ?? '',
                    ])->all(),
            ],
            'shipments' => [
                ['ID', 'Order Number', 'Courier', 'Consignment ID', 'Status', 'Delivery Fee (Paisa)', 'COD Amount (Paisa)', 'COD Collected (Paisa)', 'COD Reconciliation'],
                Shipment::query()
                    ->with('order:id,order_number')
                    ->orderByDesc('id')
                    ->limit(1000)
                    ->get()
                    ->map(fn (Shipment $s) => [
                        $s->id,
                        $s->order?->order_number ?? '',
                        $s->courier,
                        $s->consignment_id ?? '',
                        $s->status,
                        (int) $s->delivery_fee,
                        (int) $s->cod_amount,
                        $s->cod_collected_amount !== null ? (int) $s->cod_collected_amount : '',
                        $s->cod_reconciliation_status,
                    ])->all(),
            ],
            'reviews' => [
                ['ID', 'Product', 'Reviewer Name', 'Rating', 'Title', 'Status', 'Verified Purchase', 'Created At'],
                ProductReview::query()
                    ->with('product:id,name')
                    ->orderByDesc('id')
                    ->limit(1000)
                    ->get()
                    ->map(fn (ProductReview $r) => [
                        $r->id,
                        $r->product?->name ?? '',
                        $r->reviewer_name,
                        (int) $r->rating,
                        $r->title ?? '',
                        $r->status,
                        $r->is_verified_purchase ? 'Yes' : 'No',
                        $r->created_at?->toIso8601String(),
                    ])->all(),
            ],
            'audit-logs' => [
                ['ID', 'Action', 'User Email', 'Auditable Type', 'Auditable ID', 'IP Address', 'Created At'],
                AuditLog::query()
                    ->orderByDesc('created_at')
                    ->limit(1000)
                    ->get()
                    ->map(fn (AuditLog $a) => [
                        $a->id,
                        $a->action,
                        $a->user_email ?? '',
                        $a->auditable_type ?? '',
                        $a->auditable_id ?? '',
                        $a->ip_address ?? '',
                        $a->created_at?->toIso8601String(),
                    ])->all(),
            ],
            'reports-sales' => (function () use ($request) {
                $report = $this->reportingService->getComprehensiveReport($request->only(['from_date', 'to_date']));
                $rows = array_map(fn (array $day) => [
                    $day['date'],
                    $day['orders_count'],
                    $day['total_sales'],
                    number_format($day['total_sales'] / 100, 2, '.', ''),
                    $day['revenue'],
                    number_format($day['revenue'] / 100, 2, '.', ''),
                ], $report['daily_sales']);

                return [
                    ['Date', 'Orders Count', 'Gross Sales (Paisa)', 'Gross Sales (BDT)', 'Revenue (Paisa)', 'Revenue (BDT)'],
                    $rows,
                ];
            })(),
        };
    }

    private function toCsvString(array $headers, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}

