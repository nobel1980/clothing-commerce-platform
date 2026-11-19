<?php

namespace App\Modules\Reviews\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReview extends Model
{
    protected $fillable = [
        'product_id',
        'user_id',
        'order_id',
        'reviewer_name',
        'reviewer_email',
        'rating',
        'title',
        'comment',
        'status',
        'is_verified_purchase',
        'moderated_by',
        'moderated_at',
        'moderation_notes',
    ];

    protected function casts(): array
    {
        return [
            'product_id'           => 'integer',
            'user_id'              => 'integer',
            'order_id'             => 'integer',
            'rating'               => 'integer',
            'is_verified_purchase' => 'boolean',
            'moderated_by'         => 'integer',
            'moderated_at'         => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }
}

