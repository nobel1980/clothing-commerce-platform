<?php

namespace App\Modules\Checkout\Controllers;

use App\Modules\Cart\Services\CartService;
use App\Modules\Checkout\Services\CheckoutService;
use App\Modules\Orders\Models\Order;
use App\Modules\Promotions\Services\CouponService;
use App\Modules\Shipping\Services\ShippingCalculationService;
use App\Shared\Money\Money;
use App\Shared\Support\BangladeshAddressValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreCheckoutQuoteController extends Controller
{
    public function __construct(
        protected CheckoutService $checkoutService,
        protected CouponService $couponService,
        protected ShippingCalculationService $shippingService,
    ) {}

    /**
     * POST /api/checkout/quote
     * Computes authoritative line-item prices, subtotal, discount, shipping, tax, and total
     * exclusively from backend database integer sub-units (never trusting client prices).
     */
    public function quote(Request $request): JsonResponse
    {
        $rules = [
            'cart_id'                  => ['nullable', 'integer'],
            'items'                    => ['nullable', 'array'],
            'items.*.variant_id'       => ['required_without:items.*.product_variant_id', 'nullable', 'integer', 'min:1'],
            'items.*.product_variant_id'=> ['required_without:items.*.variant_id', 'nullable', 'integer', 'min:1'],
            'items.*.quantity'         => ['required', 'integer', 'min:1', 'max:' . CartService::MAX_ITEM_QUANTITY],
            'items.*.expected_unit_price' => ['nullable', 'integer', 'min:0'],
            'promo_code'               => ['nullable', 'string', 'max:64'],
            'coupon_code'              => ['nullable', 'string', 'max:64'],
            'shipping_method'          => ['nullable', 'string', Rule::in(['standard', 'express', 'pickup'])],
            'payment_method'           => ['nullable', 'string', Rule::in(['cod', 'online', 'card', 'bkash', 'nagad', 'sslcommerz'])],
            'address_id'               => ['nullable', 'integer'],
            'expected_total_amount'    => ['nullable', 'integer', 'min:0'],
            'ttl_seconds'              => ['nullable', 'integer', 'min:1', 'max:3600'],
        ];

        if ($request->has('shipping_address') && is_array($request->input('shipping_address'))) {
            $rules = array_merge($rules, BangladeshAddressValidator::rules('shipping_address'));
        }

        $validated = $request->validate($rules, BangladeshAddressValidator::messages('shipping_address'));

        $quoteData = $this->checkoutService->createQuote(
            input: $validated,
            userId: $this->resolveUserId($request),
            guestToken: $this->resolveGuestToken($request)
        );

        return response()->json([
            'data' => $quoteData,
        ]);
    }

    /**
     * POST /api/checkout/validate-coupon
     */
    public function validateCoupon(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'            => ['required_without:coupon_code', 'nullable', 'string', 'max:64'],
            'coupon_code'     => ['required_without:code', 'nullable', 'string', 'max:64'],
            'subtotal_amount' => ['required', 'integer', 'min:0'],
        ]);

        $code = (string) ($validated['code'] ?? $validated['coupon_code']);
        $subtotal = new Money((int) $validated['subtotal_amount'], 'BDT');

        $result = $this->couponService->validateAndCalculate(
            $code,
            $subtotal,
            $this->resolveUserId($request)
        );

        return response()->json([
            'data' => [
                'code'            => $result['coupon']->code,
                'type'            => $result['coupon']->type,
                'value'           => (int) $result['coupon']->value,
                'discount'        => $result['discount']->toArray(),
                'discount_amount' => $result['discount']->amount,
                'currency'        => 'BDT',
            ],
        ]);
    }

    /**
     * POST /api/checkout/shipping-rates
     */
    public function shippingRates(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'division'        => ['required', 'string', Rule::in(BangladeshAddressValidator::DIVISIONS)],
            'district'        => ['required', 'string', 'max:100'],
            'upazila_thana'   => ['nullable', 'string', 'max:100'],
            'subtotal_amount' => ['required', 'integer', 'min:0'],
        ], [
            'division.in' => 'Division must be a valid Bangladesh division: ' . implode(', ', BangladeshAddressValidator::DIVISIONS) . '.',
        ]);

        $netSubtotal = new Money((int) $validated['subtotal_amount'], 'BDT');
        $addr = [
            'division'      => $validated['division'],
            'district'      => $validated['district'],
            'upazila_thana' => $validated['upazila_thana'] ?? null,
        ];

        $standard = $this->shippingService->calculate($netSubtotal, 'standard', $addr);
        $express  = $this->shippingService->calculate($netSubtotal, 'express', $addr);
        $pickup   = $this->shippingService->calculate($netSubtotal, 'pickup', $addr);

        return response()->json([
            'data' => [
                'currency' => 'BDT',
                'rates'    => [
                    [
                        'method' => 'standard',
                        'label'  => 'Standard Courier Delivery (Bangladesh)',
                        'cost'   => $standard->toArray(),
                    ],
                    [
                        'method' => 'express',
                        'label'  => 'Express Next-Day Delivery',
                        'cost'   => $express->toArray(),
                    ],
                    [
                        'method' => 'pickup',
                        'label'  => 'Dhaka Flagship Atelier Pickup',
                        'cost'   => $pickup->toArray(),
                    ],
                ],
            ],
        ]);
    }

    /**
     * POST /api/checkout/orders
     * Validates order submission, revalidates variants & stock under row locks,
     * enforces idempotency, and persists immutable Order & OrderItem snapshots.
     */
    public function placeOrder(Request $request): JsonResponse
    {
        $rules = [
            'idempotency_key'              => ['nullable', 'string', 'max:128'],
            'quote_token'                  => ['nullable', 'string', 'max:64'],
            'cart_id'                      => ['nullable', 'integer'],
            'items'                        => ['nullable', 'array', 'min:1'],
            'items.*.variant_id'           => ['required_without:items.*.product_variant_id', 'nullable', 'integer', 'min:1'],
            'items.*.product_variant_id'   => ['required_without:items.*.variant_id', 'nullable', 'integer', 'min:1'],
            'items.*.quantity'             => ['required', 'integer', 'min:1', 'max:' . CartService::MAX_ITEM_QUANTITY],
            'items.*.expected_unit_price'  => ['nullable', 'integer', 'min:0'],
            'promo_code'                   => ['nullable', 'string', 'max:64'],
            'coupon_code'                  => ['nullable', 'string', 'max:64'],
            'shipping_method'              => ['nullable', 'string', Rule::in(['standard', 'express', 'pickup'])],
            'payment_method'               => ['required', 'string', Rule::in(['cod', 'online', 'card', 'bkash', 'nagad', 'sslcommerz'])],
            'address_id'                   => ['nullable', 'integer'],
            'expected_total_amount'        => ['nullable', 'integer', 'min:0'],
            'notes'                        => ['nullable', 'string', 'max:500'],
        ];

        if ($request->has('shipping_address')) {
            $rules = array_merge($rules, BangladeshAddressValidator::rules('shipping_address'));
        }

        if ($request->has('billing_address') && is_array($request->input('billing_address'))) {
            $rules = array_merge($rules, BangladeshAddressValidator::rules('billing_address'));
        }

        $validated = $request->validate($rules, array_merge(
            BangladeshAddressValidator::messages('shipping_address'),
            BangladeshAddressValidator::messages('billing_address')
        ));

        $idempotencyKey = $this->resolveIdempotencyKey($request, $validated);

        $result = $this->checkoutService->placeOrder(
            input: $validated,
            idempotencyKey: $idempotencyKey,
            userId: $this->resolveUserId($request),
            guestToken: $this->resolveGuestToken($request)
        );

        $statusCode = $result['idempotent_replay'] ? 200 : 201;

        return response()->json([
            'data'              => $this->checkoutService->formatOrder($result['order']),
            'idempotent_replay' => $result['idempotent_replay'],
        ], $statusCode);
    }

    /**
     * GET /api/account/orders
     */
    public function listOrders(Request $request): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if ($userId === null) {
            return response()->json(['data' => []]);
        }

        $orders = Order::query()
            ->with('items')
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->get()
            ->map(fn (Order $order) => $this->checkoutService->formatOrder($order))
            ->all();

        return response()->json([
            'data' => $orders,
        ]);
    }

    /**
     * GET /api/checkout/orders/{orderNumber}
     */
    public function showOrder(Request $request, string $orderNumber): JsonResponse
    {
        $order = Order::query()
            ->with('items')
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        $user = $request->user('sanctum') ?? $request->user();
        $guestToken = $this->resolveGuestToken($request);

        $policy = app(\App\Modules\Orders\Policies\OrderPolicy::class);
        if (!$policy->view($user, $order, $guestToken)) {
            return response()->json([
                'message' => 'You are not authorized to view this order.',
            ], 403);
        }

        return response()->json([
            'data' => $this->checkoutService->formatOrder($order),
        ]);
    }

    private function resolveIdempotencyKey(Request $request, array $validated): string
    {
        $headerKey = $request->header('Idempotency-Key') ?? $request->header('X-Idempotency-Key');
        if (is_string($headerKey) && trim($headerKey) !== '') {
            return trim($headerKey);
        }

        if (!empty($validated['idempotency_key'])) {
            return trim((string) $validated['idempotency_key']);
        }

        if (!empty($validated['quote_token'])) {
            return 'quote-order-' . trim((string) $validated['quote_token']);
        }

        return 'auto-' . Str::uuid()->toString();
    }

    private function resolveUserId(Request $request): ?int
    {
        $user = $request->user('sanctum') ?? $request->user();

        return $user?->id;
    }

    private function resolveGuestToken(Request $request): ?string
    {
        $headerToken = $request->header('X-Guest-Token');
        if (is_string($headerToken) && trim($headerToken) !== '') {
            return trim($headerToken);
        }

        $inputToken = $request->input('guest_token') ?? $request->query('guest_token');

        return is_string($inputToken) && trim($inputToken) !== '' ? trim($inputToken) : null;
    }
}
