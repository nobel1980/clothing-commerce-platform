<?php

namespace App\Modules\Cart\Controllers;

use App\Modules\Cart\Services\CartService;
use App\Shared\Support\BangladeshAddressValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class CartController extends Controller
{
    public function __construct(
        protected CartService $cartService
    ) {}

    /**
     * GET /api/cart
     */
    public function show(Request $request): JsonResponse
    {
        $cart = $this->cartService->resolveCart(
            $this->resolveUserId($request),
            $this->resolveGuestToken($request),
            true
        );

        return response()->json([
            'data' => $this->cartService->formatCart($cart),
        ]);
    }

    /**
     * POST /api/cart/items
     */
    public function addItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'variant_id'         => ['required_without:product_variant_id', 'nullable', 'integer', 'min:1'],
            'product_variant_id' => ['required_without:variant_id', 'nullable', 'integer', 'min:1'],
            'quantity'           => ['required', 'integer', 'min:1', 'max:' . CartService::MAX_ITEM_QUANTITY],
            'guest_token'        => ['nullable', 'string', 'max:100'],
        ]);

        $variantId = (int) ($validated['variant_id'] ?? $validated['product_variant_id']);
        $quantity  = (int) $validated['quantity'];

        $cart = $this->cartService->addItem(
            $this->resolveUserId($request),
            $this->resolveGuestToken($request),
            $variantId,
            $quantity
        );

        return response()->json([
            'data' => $this->cartService->formatCart($cart),
        ], 201);
    }

    /**
     * PUT/PATCH /api/cart/items/{item}
     */
    public function updateItem(Request $request, int $item): JsonResponse
    {
        $validated = $request->validate([
            'quantity'    => ['required', 'integer', 'min:1', 'max:' . CartService::MAX_ITEM_QUANTITY],
            'guest_token' => ['nullable', 'string', 'max:100'],
        ]);

        $cart = $this->cartService->updateItemQuantity(
            $this->resolveUserId($request),
            $this->resolveGuestToken($request),
            $item,
            (int) $validated['quantity']
        );

        return response()->json([
            'data' => $this->cartService->formatCart($cart),
        ]);
    }

    /**
     * DELETE /api/cart/items/{item}
     */
    public function removeItem(Request $request, int $item): JsonResponse
    {
        $cart = $this->cartService->removeItem(
            $this->resolveUserId($request),
            $this->resolveGuestToken($request),
            $item
        );

        return response()->json([
            'data' => $this->cartService->formatCart($cart),
        ]);
    }

    /**
     * DELETE /api/cart
     */
    public function clear(Request $request): JsonResponse
    {
        $cart = $this->cartService->clearCart(
            $this->resolveUserId($request),
            $this->resolveGuestToken($request)
        );

        return response()->json([
            'data' => $this->cartService->formatCart($cart),
        ]);
    }

    /**
     * POST /api/cart/merge
     */
    public function merge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'guest_token' => ['required', 'string', 'max:100'],
        ]);

        $userId = $this->resolveUserId($request);
        if ($userId === null) {
            return response()->json([
                'message' => 'Authentication is required to merge a guest cart.',
            ], 401);
        }

        $cart = $this->cartService->mergeGuestCart($validated['guest_token'], $userId);

        return response()->json([
            'data' => $this->cartService->formatCart($cart),
        ]);
    }

    /**
     * PUT /api/cart/address
     */
    public function selectAddress(Request $request): JsonResponse
    {
        $rules = [
            'address_id'      => ['required_without:shipping_address', 'nullable', 'integer'],
            'shipping_method' => ['nullable', 'string', Rule::in(['standard', 'express', 'pickup'])],
            'guest_token'     => ['nullable', 'string', 'max:100'],
        ];

        if ($request->has('shipping_address')) {
            $rules = array_merge($rules, BangladeshAddressValidator::rules('shipping_address'));
        }

        $validated = $request->validate($rules, BangladeshAddressValidator::messages('shipping_address'));

        $cart = $this->cartService->selectAddress(
            $this->resolveUserId($request),
            $this->resolveGuestToken($request),
            $validated
        );

        return response()->json([
            'data' => $this->cartService->formatCart($cart),
        ]);
    }

    /**
     * POST /api/cart/coupon
     */
    public function applyCoupon(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'        => ['required_without:coupon_code', 'nullable', 'string', 'max:64'],
            'coupon_code' => ['required_without:code', 'nullable', 'string', 'max:64'],
            'guest_token' => ['nullable', 'string', 'max:100'],
        ]);

        $code = (string) ($validated['code'] ?? $validated['coupon_code']);

        $cart = $this->cartService->applyCoupon(
            $this->resolveUserId($request),
            $this->resolveGuestToken($request),
            $code
        );

        return response()->json([
            'data' => $this->cartService->formatCart($cart),
        ]);
    }

    /**
     * DELETE /api/cart/coupon
     */
    public function removeCoupon(Request $request): JsonResponse
    {
        $cart = $this->cartService->removeCoupon(
            $this->resolveUserId($request),
            $this->resolveGuestToken($request)
        );

        return response()->json([
            'data' => $this->cartService->formatCart($cart),
        ]);
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

