<?php

namespace App\Modules\Promotions\Services;

use App\Modules\Promotions\Models\Coupon;
use App\Modules\Promotions\Models\CouponRedemption;
use App\Shared\Exceptions\Checkout\CouponExpiredException;
use App\Shared\Exceptions\Checkout\InvalidCouponException;
use App\Shared\Money\Money;

class CouponService
{
    /**
     * Validate a coupon code against an authoritative subtotal in integer subunits (BDT paisa).
     *
     * @return array{coupon: Coupon, discount: Money}
     */
    public function validateAndCalculate(
        string $rawCode,
        Money $subtotal,
        ?int $userId = null,
        bool $lockForUpdate = false
    ): array {
        $code = strtoupper(trim($rawCode));
        if ($code === '') {
            throw new InvalidCouponException('Coupon code cannot be empty.');
        }

        $this->ensureBuiltinCouponsIfNeeded($code);

        $query = Coupon::query()->where('code', $code);
        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        /** @var Coupon|null $coupon */
        $coupon = $query->first();

        if (!$coupon) {
            throw new InvalidCouponException("Coupon code '{$code}' does not exist.", ['coupon_code' => $code]);
        }

        if (!$coupon->is_active) {
            throw new InvalidCouponException("Coupon code '{$code}' is no longer active.", ['coupon_code' => $code]);
        }

        if ($coupon->starts_at !== null && $coupon->starts_at->gt(now())) {
            throw new InvalidCouponException("Coupon code '{$code}' is not yet active.", ['coupon_code' => $code]);
        }

        if ($coupon->isExpired()) {
            throw new CouponExpiredException($code);
        }

        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            throw new InvalidCouponException(
                "Coupon code '{$code}' has reached its maximum usage limit.",
                ['coupon_code' => $code]
            );
        }

        if ($userId !== null && $coupon->usage_limit_per_user !== null) {
            $userRedemptions = CouponRedemption::query()
                ->where('coupon_id', $coupon->id)
                ->where('user_id', $userId)
                ->count();

            if ($userRedemptions >= $coupon->usage_limit_per_user) {
                throw new InvalidCouponException(
                    "You have already reached the usage limit for coupon '{$code}'.",
                    ['coupon_code' => $code]
                );
            }
        }

        if ($subtotal->amount < (int) $coupon->min_order_amount) {
            $minDisplay = number_format($coupon->min_order_amount / 100, 2);
            throw new InvalidCouponException(
                "Coupon '{$code}' requires a minimum order subtotal of ৳{$minDisplay} BDT.",
                [
                    'coupon_code'      => $code,
                    'min_order_amount' => (int) $coupon->min_order_amount,
                    'current_subtotal' => $subtotal->amount,
                ]
            );
        }

        $discountAmount = 0;
        if ($coupon->type === 'percentage') {
            $discountAmount = intdiv($subtotal->amount * (int) $coupon->value, 100);
            if ($coupon->max_discount_amount !== null) {
                $discountAmount = min($discountAmount, (int) $coupon->max_discount_amount);
            }
        } else {
            $discountAmount = (int) $coupon->value;
        }

        $discountAmount = max(0, min($discountAmount, $subtotal->amount));

        return [
            'coupon'   => $coupon,
            'discount' => new Money($discountAmount, $subtotal->currency),
        ];
    }

    /**
     * Record a coupon redemption inside an order placement DB transaction.
     */
    public function recordRedemption(Coupon $coupon, int $orderId, ?int $userId, Money $discount): CouponRedemption
    {
        $coupon->increment('used_count');

        return CouponRedemption::query()->create([
            'coupon_id'       => $coupon->id,
            'user_id'         => $userId,
            'order_id'        => $orderId,
            'discount_amount' => $discount->amount,
        ]);
    }

    private function ensureBuiltinCouponsIfNeeded(string $code): void
    {
        if (!in_array($code, ['WELCOME10', 'ARCHIVE15'], true)) {
            return;
        }

        if (Coupon::query()->where('code', $code)->exists()) {
            return;
        }

        Coupon::query()->create([
            'code'             => $code,
            'description'      => $code === 'WELCOME10' ? '10% welcome discount' : '15% archive seasonal discount',
            'type'             => 'percentage',
            'value'            => $code === 'WELCOME10' ? 10 : 15,
            'min_order_amount' => 0,
            'currency'         => 'BDT',
            'is_active'        => true,
        ]);
    }
}

