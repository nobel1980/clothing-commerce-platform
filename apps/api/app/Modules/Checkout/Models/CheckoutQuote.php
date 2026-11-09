<?php

namespace App\Modules\Checkout\Models;

use App\Modules\Cart\Models\Cart;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Promotions\Models\Coupon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckoutQuote extends Model
{
    protected $fillable = [
        'quote_token',
        'cart_id',
        'user_id',
        'guest_token',
        'status',
        'currency',
        'shipping_method',
        'payment_method',
        'coupon_id',
        'coupon_code',
        'subtotal_amount',
        'discount_amount',
        'shipping_amount',
        'tax_amount',
        'total_amount',
        'items_snapshot',
        'shipping_address_snapshot',
        'billing_address_snapshot',
        'coupon_snapshot',
        'pricing_hash',
        'expires_at',
        'converted_order_id',
    ];

    protected function casts(): array
    {
        return [
            'cart_id'                   => 'integer',
            'user_id'                   => 'integer',
            'coupon_id'                 => 'integer',
            'subtotal_amount'           => 'integer',
            'discount_amount'           => 'integer',
            'shipping_amount'           => 'integer',
            'tax_amount'                => 'integer',
            'total_amount'              => 'integer',
            'items_snapshot'            => 'array',
            'shipping_address_snapshot' => 'array',
            'billing_address_snapshot'  => 'array',
            'coupon_snapshot'           => 'array',
            'expires_at'                => 'datetime',
            'converted_order_id'        => 'integer',
        ];
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired'
            || ($this->expires_at !== null && $this->expires_at->lte(now()));
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function convertedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'converted_order_id');
    }
}

