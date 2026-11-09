<?php

namespace App\Modules\Promotions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponRedemption extends Model
{
    protected $fillable = [
        'coupon_id',
        'user_id',
        'order_id',
        'discount_amount',
    ];

    protected function casts(): array
    {
        return [
            'coupon_id'       => 'integer',
            'user_id'         => 'integer',
            'order_id'        => 'integer',
            'discount_amount' => 'integer',
        ];
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}

