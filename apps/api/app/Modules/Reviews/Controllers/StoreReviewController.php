<?php

namespace App\Modules\Reviews\Controllers;

use App\Modules\Catalog\Models\Product;
use App\Modules\Orders\Models\Order;
use App\Modules\Reviews\Models\ProductReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class StoreReviewController extends Controller
{
    /**
     * GET /api/catalog/products/{product:slug}/reviews
     */
    public function index(Product $product): JsonResponse
    {
        $reviews = ProductReview::query()
            ->where('product_id', $product->id)
            ->where('status', 'approved')
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json([
            'data' => collect($reviews->items())->map(fn (ProductReview $r) => [
                'id'                   => $r->id,
                'reviewer_name'        => $r->reviewer_name,
                'rating'               => (int) $r->rating,
                'title'                => $r->title,
                'comment'              => $r->comment,
                'is_verified_purchase' => (bool) $r->is_verified_purchase,
                'created_at'           => $r->created_at?->toIso8601String(),
            ])->all(),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page'    => $reviews->lastPage(),
                'per_page'     => $reviews->perPage(),
                'total'        => $reviews->total(),
            ],
        ]);
    }

    /**
     * POST /api/catalog/products/{product:slug}/reviews
     */
    public function store(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'reviewer_name'  => ['required', 'string', 'max:191'],
            'reviewer_email' => ['nullable', 'email', 'max:191'],
            'rating'         => ['required', 'integer', 'min:1', 'max:5'],
            'title'          => ['nullable', 'string', 'max:191'],
            'comment'        => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $user = $request->user('sanctum');
        $isVerified = false;
        $orderId = null;

        if ($user) {
            $verifiedOrder = Order::query()
                ->where('user_id', $user->id)
                ->whereIn('status', ['delivered', 'shipped', 'confirmed'])
                ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
                ->latest('id')
                ->first();

            if ($verifiedOrder) {
                $isVerified = true;
                $orderId = $verifiedOrder->id;
            }
        }

        $review = ProductReview::create([
            'product_id'           => $product->id,
            'user_id'              => $user?->id,
            'order_id'             => $orderId,
            'reviewer_name'        => $validated['reviewer_name'],
            'reviewer_email'       => $validated['reviewer_email'] ?? $user?->email,
            'rating'               => (int) $validated['rating'],
            'title'                => $validated['title'] ?? null,
            'comment'              => $validated['comment'],
            'status'               => 'pending',
            'is_verified_purchase' => $isVerified,
        ]);

        return response()->json([
            'message' => 'Review submitted and is pending moderation.',
            'data'    => [
                'id'                   => $review->id,
                'status'               => $review->status,
                'rating'               => $review->rating,
                'is_verified_purchase' => $review->is_verified_purchase,
            ],
        ], 201);
    }
}

