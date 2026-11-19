<?php

namespace App\Modules\Promotions\Controllers;

use App\Modules\Identity\Services\AuditService;
use App\Modules\Promotions\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class AdminCouponController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    private function authorizePermission(Request $request, string $permission): void
    {
        $user = $request->user('sanctum') ?? $request->user();

        abort_unless(
            $user !== null && ($user->can($permission) || $user->isSuperAdmin()),
            403,
            'You do not have permission to perform this promotions action.'
        );
    }

    /**
     * GET /api/admin/promotions/coupons
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'promotions.view');

        $query = Coupon::query()
            ->withCount('redemptions')
            ->orderByDesc('created_at');

        if ($request->filled('type')) {
            $query->where('type', (string) $request->query('type'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $paginator = $query->paginate($perPage);

        $items = collect($paginator->items())
            ->map(fn (Coupon $c) => $this->formatCoupon($c))
            ->all();

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    /**
     * GET /api/admin/promotions/coupons/{coupon}
     */
    public function show(Request $request, Coupon $coupon): JsonResponse
    {
        $this->authorizePermission($request, 'promotions.view');

        return response()->json([
            'data' => $this->formatCoupon($coupon->loadCount('redemptions')),
        ]);
    }

    /**
     * POST /api/admin/promotions/coupons
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'promotions.manage');

        $validated = $request->validate([
            'code'                 => ['required', 'string', 'max:60', 'unique:coupons,code'],
            'description'          => ['nullable', 'string', 'max:500'],
            'type'                 => ['required', 'string', Rule::in(['percentage', 'fixed_amount'])],
            'value'                => ['required', 'integer', 'min:1'],
            'min_order_amount'     => ['nullable', 'integer', 'min:0'],
            'max_discount_amount'  => ['nullable', 'integer', 'min:0'],
            'usage_limit'          => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_user' => ['nullable', 'integer', 'min:1'],
            'currency'             => ['nullable', 'string', 'size:3'],
            'starts_at'            => ['nullable', 'date'],
            'expires_at'           => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active'            => ['nullable', 'boolean'],
        ]);

        if ($validated['type'] === 'percentage' && $validated['value'] > 10000) {
            return response()->json([
                'message' => 'Percentage value cannot exceed 100% (100 or 10000 basis points).',
                'errors'  => ['value' => ['Percentage value cannot exceed 100%.']],
            ], 422);
        }

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['currency'] = strtoupper($validated['currency'] ?? 'BDT');
        $validated['min_order_amount'] = (int) ($validated['min_order_amount'] ?? 0);
        $validated['is_active'] = array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true;

        $coupon = Coupon::create($validated);

        $actor = $request->user('sanctum') ?? $request->user();
        $this->auditService->log('coupon.created', $actor, $coupon, null, [
            'code'  => $coupon->code,
            'type'  => $coupon->type,
            'value' => $coupon->value,
        ]);

        return response()->json([
            'message' => 'Coupon created successfully.',
            'data'    => $this->formatCoupon($coupon),
        ], 201);
    }

    /**
     * PUT /api/admin/promotions/coupons/{coupon}
     */
    public function update(Request $request, Coupon $coupon): JsonResponse
    {
        $this->authorizePermission($request, 'promotions.manage');

        $validated = $request->validate([
            'code'                 => ['sometimes', 'string', 'max:60', Rule::unique('coupons', 'code')->ignore($coupon->id)],
            'description'          => ['nullable', 'string', 'max:500'],
            'type'                 => ['sometimes', 'string', Rule::in(['percentage', 'fixed_amount'])],
            'value'                => ['sometimes', 'integer', 'min:1'],
            'min_order_amount'     => ['nullable', 'integer', 'min:0'],
            'max_discount_amount'  => ['nullable', 'integer', 'min:0'],
            'usage_limit'          => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_user' => ['nullable', 'integer', 'min:1'],
            'starts_at'            => ['nullable', 'date'],
            'expires_at'           => ['nullable', 'date'],
            'is_active'            => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper(trim($validated['code']));
        }

        $oldValues = $coupon->only(['code', 'type', 'value', 'min_order_amount', 'is_active']);
        $coupon->update($validated);

        $actor = $request->user('sanctum') ?? $request->user();
        $this->auditService->log('coupon.updated', $actor, $coupon, $oldValues, $validated);

        return response()->json([
            'message' => 'Coupon updated successfully.',
            'data'    => $this->formatCoupon($coupon->fresh() ?? $coupon),
        ]);
    }

    /**
     * POST /api/admin/promotions/coupons/{coupon}/toggle
     */
    public function toggle(Request $request, Coupon $coupon): JsonResponse
    {
        $this->authorizePermission($request, 'promotions.manage');

        $oldState = (bool) $coupon->is_active;
        $coupon->update(['is_active' => !$oldState]);

        $actor = $request->user('sanctum') ?? $request->user();
        $this->auditService->log(
            'coupon.status_toggled',
            $actor,
            $coupon,
            ['is_active' => $oldState],
            ['is_active' => !$oldState]
        );

        return response()->json([
            'message' => 'Coupon status updated.',
            'data'    => $this->formatCoupon($coupon->fresh() ?? $coupon),
        ]);
    }

    /**
     * DELETE /api/admin/promotions/coupons/{coupon}
     */
    public function destroy(Request $request, Coupon $coupon): JsonResponse
    {
        $this->authorizePermission($request, 'promotions.manage');

        $actor = $request->user('sanctum') ?? $request->user();
        $oldValues = $coupon->only(['id', 'code', 'type', 'value']);

        $coupon->delete();
        $this->auditService->log('coupon.deleted', $actor, null, $oldValues, null);

        return response()->json([
            'message' => 'Coupon deleted successfully.',
        ]);
    }

    private function formatCoupon(Coupon $coupon): array
    {
        return [
            'id'                   => $coupon->id,
            'code'                 => $coupon->code,
            'description'          => $coupon->description,
            'type'                 => $coupon->type,
            'value'                => (int) $coupon->value,
            'min_order_amount'     => (int) $coupon->min_order_amount,
            'max_discount_amount'  => $coupon->max_discount_amount !== null ? (int) $coupon->max_discount_amount : null,
            'usage_limit'          => $coupon->usage_limit !== null ? (int) $coupon->usage_limit : null,
            'usage_limit_per_user' => $coupon->usage_limit_per_user !== null ? (int) $coupon->usage_limit_per_user : null,
            'used_count'           => (int) $coupon->used_count,
            'redemptions_count'    => (int) ($coupon->redemptions_count ?? $coupon->used_count),
            'currency'             => $coupon->currency ?? 'BDT',
            'starts_at'            => $coupon->starts_at?->toIso8601String(),
            'expires_at'           => $coupon->expires_at?->toIso8601String(),
            'is_active'            => (bool) $coupon->is_active,
            'is_expired'           => $coupon->isExpired(),
            'created_at'           => $coupon->created_at?->toIso8601String(),
        ];
    }
}

