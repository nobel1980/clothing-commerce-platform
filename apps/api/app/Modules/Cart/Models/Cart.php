<?php

namespace App\Modules\Cart\Models;

use App\Modules\Identity\Models\Address;
use App\Modules\Identity\Models\User;
use App\Modules\Promotions\Models\Coupon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    protected $fillable = [
        'user_id',
        'guest_token',
        'status',
        'currency',
        'coupon_id',
        'coupon_code',
        'shipping_address_id',
        'shipping_address_data',
        'billing_address_data',
        'shipping_method',
        'notes',
        'merged_into_cart_id',
        'merged_at',
        'converted_order_id',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id'               => 'integer',
            'coupon_id'             => 'integer',
            'shipping_address_id'   => 'integer',
            'shipping_address_data' => 'array',
            'billing_address_data'  => 'array',
            'merged_into_cart_id'   => 'integer',
            'converted_order_id'    => 'integer',
            'merged_at'             => 'datetime',
            'expires_at'            => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'shipping_address_id');
    }
}

