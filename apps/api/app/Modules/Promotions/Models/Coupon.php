<?php

namespace App\Modules\Promotions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $fillable = [
        'code',
        'description',
        'type',
        'value',
        'min_order_amount',
        'max_discount_amount',
        'usage_limit',
        'usage_limit_per_user',
        'used_count',
        'currency',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value'                => 'integer',
            'min_order_amount'     => 'integer',
            'max_discount_amount'  => 'integer',
            'usage_limit'          => 'integer',
            'usage_limit_per_user' => 'integer',
            'used_count'           => 'integer',
            'starts_at'            => 'datetime',
            'expires_at'           => 'datetime',
            'is_active'            => 'boolean',
        ];
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->lte(now());
    }
}

