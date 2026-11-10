<?php

namespace App\Modules\Orders\Models;

use App\Modules\Cart\Models\Cart;
use App\Modules\Checkout\Models\CheckoutQuote;
use App\Modules\Identity\Models\User;
use App\Modules\Payments\Models\PaymentRefund;
use App\Modules\Payments\Models\PaymentTransaction;
use App\Modules\Promotions\Models\Coupon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'idempotency_key',
        'user_id',
        'guest_token',
        'cart_id',
        'checkout_quote_id',
        'stock_reservation_id',
        'status',
        'payment_method',
        'payment_status',
        'fulfilment_status',
        'currency',
        'subtotal_amount',
        'discount_amount',
        'shipping_amount',
        'tax_amount',
        'total_amount',
        'coupon_id',
        'coupon_code',
        'coupon_snapshot',
        'shipping_method',
        'shipping_address_snapshot',
        'billing_address_snapshot',
        'payment_preparation_data',
        'notes',
        'placed_at',
        'confirmed_at',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'user_id'                   => 'integer',
            'cart_id'                   => 'integer',
            'checkout_quote_id'         => 'integer',
            'stock_reservation_id'      => 'integer',
            'subtotal_amount'           => 'integer',
            'discount_amount'           => 'integer',
            'shipping_amount'           => 'integer',
            'tax_amount'                => 'integer',
            'total_amount'              => 'integer',
            'coupon_id'                 => 'integer',
            'coupon_snapshot'           => 'array',
            'shipping_address_snapshot' => 'array',
            'billing_address_snapshot'  => 'array',
            'payment_preparation_data'  => 'array',
            'placed_at'                 => 'datetime',
            'confirmed_at'              => 'datetime',
            'shipped_at'                => 'datetime',
            'delivered_at'              => 'datetime',
            'cancelled_at'              => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('id');
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class)->orderByDesc('id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class)->orderByDesc('id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function checkoutQuote(): BelongsTo
    {
        return $this->belongsTo(CheckoutQuote::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}
