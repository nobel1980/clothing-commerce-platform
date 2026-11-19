<?php

namespace App\Modules\Reporting\Controllers;

use App\Modules\Reporting\Services\ReportingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AdminDashboardController extends Controller
{
    public function __construct(
        protected ReportingService $reportingService
    ) {}

    /**
     * GET /api/admin/dashboard/metrics
     */
    public function metrics(Request $request): JsonResponse
    {
        $user = $request->user('sanctum') ?? $request->user();

        abort_unless(
            $user !== null && (
                $user->can('reports.view')
                || $user->can('orders.view')
                || $user->isSuperAdmin()
            ),
            403,
            'You do not have permission to view dashboard metrics.'
        );

        $filters = $request->only(['from_date', 'to_date']);

        return response()->json([
            'data' => $this->reportingService->getDashboardMetrics($filters),
        ]);
    }
}

