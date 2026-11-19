<?php

namespace App\Modules\Reviews\Controllers;

use App\Modules\Identity\Services\AuditService;
use App\Modules\Reviews\Models\ProductReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class AdminReviewController extends Controller
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
            'You do not have permission to perform this review action.'
        );
    }

    /**
     * GET /api/admin/reviews
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'reviews.view');

        $query = ProductReview::query()
            ->with(['product:id,name,slug', 'user:id,name,email', 'moderator:id,name,email'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        if ($request->filled('rating')) {
            $query->where('rating', (int) $request->query('rating'));
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', (int) $request->query('product_id'));
        }

        if ($request->filled('is_verified_purchase')) {
            $query->where('is_verified_purchase', filter_var($request->query('is_verified_purchase'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('reviewer_name', 'like', "%{$search}%")
                  ->orWhere('reviewer_email', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhere('comment', 'like', "%{$search}%")
                  ->orWhereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$search}%"));
            });
        }

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $paginator = $query->paginate($perPage);

        $items = collect($paginator->items())
            ->map(fn (ProductReview $r) => $this->formatReview($r))
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
     * GET /api/admin/reviews/{review}
     */
    public function show(Request $request, ProductReview $review): JsonResponse
    {
        $this->authorizePermission($request, 'reviews.view');

        return response()->json([
            'data' => $this->formatReview($review->load(['product:id,name,slug', 'user:id,name,email', 'moderator:id,name,email'])),
        ]);
    }

    /**
     * POST /api/admin/reviews/{review}/moderate
     */
    public function moderate(Request $request, ProductReview $review): JsonResponse
    {
        $this->authorizePermission($request, 'reviews.manage');

        $validated = $request->validate([
            'status'           => ['required', 'string', Rule::in(['approved', 'rejected', 'pending'])],
            'moderation_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $actor = $request->user('sanctum') ?? $request->user();
        $oldStatus = $review->status;

        $review->update([
            'status'           => $validated['status'],
            'moderation_notes' => $validated['moderation_notes'] ?? $review->moderation_notes,
            'moderated_by'     => $actor?->id,
            'moderated_at'     => now(),
        ]);

        $this->auditService->log(
            "review.{$validated['status']}",
            $actor,
            $review,
            ['status' => $oldStatus],
            ['status' => $validated['status'], 'moderation_notes' => $validated['moderation_notes'] ?? null]
        );

        return response()->json([
            'message' => "Review status updated to {$validated['status']}.",
            'data'    => $this->formatReview($review->fresh(['product:id,name,slug', 'user:id,name,email', 'moderator:id,name,email']) ?? $review),
        ]);
    }

    /**
     * DELETE /api/admin/reviews/{review}
     */
    public function destroy(Request $request, ProductReview $review): JsonResponse
    {
        $this->authorizePermission($request, 'reviews.manage');

        $actor = $request->user('sanctum') ?? $request->user();
        $oldValues = $review->only(['id', 'product_id', 'reviewer_name', 'rating', 'status']);

        $review->delete();
        $this->auditService->log('review.deleted', $actor, null, $oldValues, null);

        return response()->json([
            'message' => 'Review deleted successfully.',
        ]);
    }

    private function formatReview(ProductReview $review): array
    {
        return [
            'id'                   => $review->id,
            'product_id'           => $review->product_id,
            'product'              => $review->product ? [
                'id'   => $review->product->id,
                'name' => $review->product->name,
                'slug' => $review->product->slug,
            ] : null,
            'user_id'              => $review->user_id,
            'order_id'             => $review->order_id,
            'reviewer_name'        => $review->reviewer_name,
            'reviewer_email'       => $review->reviewer_email,
            'rating'               => (int) $review->rating,
            'title'                => $review->title,
            'comment'              => $review->comment,
            'status'               => $review->status,
            'is_verified_purchase' => (bool) $review->is_verified_purchase,
            'moderated_by'         => $review->moderated_by,
            'moderator'            => $review->moderator ? [
                'id'    => $review->moderator->id,
                'name'  => $review->moderator->name,
                'email' => $review->moderator->email,
            ] : null,
            'moderated_at'         => $review->moderated_at?->toIso8601String(),
            'moderation_notes'     => $review->moderation_notes,
            'created_at'           => $review->created_at?->toIso8601String(),
            'updated_at'           => $review->updated_at?->toIso8601String(),
        ];
    }
}

