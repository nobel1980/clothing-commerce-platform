<?php

namespace App\Modules\Reporting\Controllers;

use App\Modules\Reporting\Services\ReportingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AdminReportController extends Controller
{
    public function __construct(
        protected ReportingService $reportingService
    ) {}

    private function authorizePermission(Request $request): void
    {
        $user = $request->user('sanctum') ?? $request->user();

        abort_unless(
            $user !== null && ($user->can('reports.view') || $user->isSuperAdmin()),
            403,
            'You do not have permission to view analytics reports.'
        );
    }

    /**
     * GET /api/admin/reports/overview
     */
    public function overview(Request $request): JsonResponse
    {
        $this->authorizePermission($request);

        return response()->json([
            'data' => $this->reportingService->getComprehensiveReport(
                $request->only(['from_date', 'to_date'])
            ),
        ]);
    }

    /**
     * GET /api/admin/reports/sales
     */
    public function sales(Request $request): JsonResponse
    {
        $this->authorizePermission($request);

        $report = $this->reportingService->getComprehensiveReport(
            $request->only(['from_date', 'to_date'])
        );

        return response()->json([
            'data' => [
                'currency'            => 'BDT',
                'total_sales'         => $report['metrics']['total_sales'],
                'total_sales_count'   => $report['metrics']['total_sales_count'],
                'revenue'             => $report['metrics']['revenue'],
                'average_order_value' => $report['metrics']['average_order_value'],
                'daily_sales'         => $report['daily_sales'],
                'payment_breakdown'   => $report['payment_method_breakdown'],
                'status_breakdown'    => $report['metrics']['status_breakdown'],
            ],
        ]);
    }

    /**
     * GET /api/admin/reports/best-sellers
     */
    public function bestSellers(Request $request): JsonResponse
    {
        $this->authorizePermission($request);

        $limit = min(max((int) $request->query('limit', 25), 1), 100);

        return response()->json([
            'data' => $this->reportingService->getBestSellingProducts(
                limit: $limit,
                fromDate: $request->query('from_date'),
                toDate: $request->query('to_date')
            ),
        ]);
    }

    /**
     * GET /api/admin/reports/low-stock
     */
    public function lowStock(Request $request): JsonResponse
    {
        $this->authorizePermission($request);

        $limit = min(max((int) $request->query('limit', 50), 1), 200);

        return response()->json([
            'data' => $this->reportingService->getLowStockProducts($limit),
        ]);
    }
}

