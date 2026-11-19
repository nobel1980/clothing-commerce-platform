<?php

namespace App\Modules\Customers\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\AuditService;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class AdminCustomerController extends Controller
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
            'You do not have permission to perform this customer action.'
        );
    }

    /**
     * GET /api/admin/customers
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'customers.view');

        $query = User::query()
            ->where('role', 'customer')
            ->with(['addresses'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $paginator = $query->paginate($perPage);

        $customerIds = collect($paginator->items())->pluck('id')->all();

        $orderAggregates = [];
        if (!empty($customerIds)) {
            $rows = Order::query()
                ->whereIn('user_id', $customerIds)
                ->selectRaw('user_id, COUNT(*) as orders_count, COALESCE(SUM(CASE WHEN status != ? THEN total_amount ELSE 0 END), 0) as total_spent', ['cancelled'])
                ->groupBy('user_id')
                ->get();

            foreach ($rows as $row) {
                $orderAggregates[(int) $row->user_id] = [
                    'orders_count' => (int) $row->orders_count,
                    'total_spent'  => (int) $row->total_spent,
                ];
            }
        }

        $items = collect($paginator->items())
            ->map(function (User $customer) use ($orderAggregates) {
                $stats = $orderAggregates[$customer->id] ?? ['orders_count' => 0, 'total_spent' => 0];
                return $this->formatCustomer($customer, $stats['orders_count'], $stats['total_spent']);
            })
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
     * GET /api/admin/customers/{customer}
     */
    public function show(Request $request, User $customer): JsonResponse
    {
        $this->authorizePermission($request, 'customers.view');

        abort_unless($customer->role === 'customer', 404, 'Customer not found.');

        $customer->load('addresses');

        $orders = Order::query()
            ->where('user_id', $customer->id)
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $ordersCount = Order::query()->where('user_id', $customer->id)->count();
        $totalSpent = (int) Order::query()
            ->where('user_id', $customer->id)
            ->where('status', '!=', 'cancelled')
            ->sum('total_amount');

        $payload = $this->formatCustomer($customer, $ordersCount, $totalSpent);
        $payload['recent_orders'] = $orders->map(fn (Order $o) => [
            'id'                => $o->id,
            'order_number'      => $o->order_number,
            'status'            => $o->status,
            'payment_status'    => $o->payment_status,
            'fulfilment_status' => $o->fulfilment_status,
            'payment_method'    => $o->payment_method,
            'total_amount'      => (int) $o->total_amount,
            'currency'          => $o->currency,
            'placed_at'         => $o->placed_at?->toIso8601String() ?? $o->created_at?->toIso8601String(),
        ])->all();

        return response()->json([
            'data' => $payload,
        ]);
    }

    /**
     * PUT /api/admin/customers/{customer}
     */
    public function update(Request $request, User $customer): JsonResponse
    {
        $this->authorizePermission($request, 'customers.manage');

        abort_unless($customer->role === 'customer', 404, 'Customer not found.');

        $validated = $request->validate([
            'name'   => ['sometimes', 'string', 'max:191'],
            'phone'  => ['nullable', 'string', 'max:30'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'suspended', 'inactive'])],
        ]);

        $actor = $request->user('sanctum') ?? $request->user();
        $oldValues = $customer->only(['name', 'phone', 'status']);

        $customer->update($validated);

        if (isset($validated['status']) && $validated['status'] !== 'active') {
            $customer->tokens()->delete();
        }

        $this->auditService->log('customer.updated', $actor, $customer, $oldValues, $validated);

        $ordersCount = Order::query()->where('user_id', $customer->id)->count();
        $totalSpent = (int) Order::query()
            ->where('user_id', $customer->id)
            ->where('status', '!=', 'cancelled')
            ->sum('total_amount');

        return response()->json([
            'message' => 'Customer updated successfully.',
            'data'    => $this->formatCustomer($customer->fresh('addresses') ?? $customer, $ordersCount, $totalSpent),
        ]);
    }

    /**
     * POST /api/admin/customers/{customer}/status
     */
    public function toggleStatus(Request $request, User $customer): JsonResponse
    {
        $this->authorizePermission($request, 'customers.manage');

        abort_unless($customer->role === 'customer', 404, 'Customer not found.');

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['active', 'suspended', 'inactive'])],
        ]);

        $actor = $request->user('sanctum') ?? $request->user();
        $oldStatus = $customer->status;

        $customer->update(['status' => $validated['status']]);

        if ($validated['status'] !== 'active') {
            $customer->tokens()->delete();
        }

        $this->auditService->log(
            "customer.status_{$validated['status']}",
            $actor,
            $customer,
            ['status' => $oldStatus],
            ['status' => $validated['status']]
        );

        $ordersCount = Order::query()->where('user_id', $customer->id)->count();
        $totalSpent = (int) Order::query()
            ->where('user_id', $customer->id)
            ->where('status', '!=', 'cancelled')
            ->sum('total_amount');

        return response()->json([
            'message' => "Customer status updated to {$validated['status']}.",
            'data'    => $this->formatCustomer($customer->fresh('addresses') ?? $customer, $ordersCount, $totalSpent),
        ]);
    }

    private function formatCustomer(User $customer, int $ordersCount = 0, int $totalSpent = 0): array
    {
        return [
            'id'                => $customer->id,
            'name'              => $customer->name,
            'email'             => $customer->email,
            'phone'             => $customer->phone,
            'status'            => $customer->status,
            'orders_count'      => $ordersCount,
            'total_spent'       => $totalSpent,
            'currency'          => 'BDT',
            'email_verified_at' => $customer->email_verified_at?->toIso8601String(),
            'last_login_at'     => $customer->last_login_at?->toIso8601String(),
            'addresses'         => $customer->relationLoaded('addresses')
                ? $customer->addresses->map(fn ($addr) => [
                    'id'             => $addr->id,
                    'recipient_name' => $addr->recipient_name,
                    'phone'          => $addr->phone,
                    'division'       => $addr->division,
                    'district'       => $addr->district,
                    'upazila'        => $addr->upazila,
                    'address_line_1' => $addr->address_line_1,
                    'is_default'     => (bool) $addr->is_default,
                ])->all()
                : [],
            'created_at'        => $customer->created_at?->toIso8601String(),
        ];
    }
}

