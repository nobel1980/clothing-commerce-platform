<?php

namespace App\Modules\Shipping\Services;

use App\Shared\Money\Money;

class ShippingCalculationService
{
    public const FREE_STANDARD_SHIPPING_THRESHOLD_PAISA = 500000; // 5,000.00 BDT

    /**
     * Calculate authoritative shipping cost in integer BDT paisa based on Bangladesh
     * division/district and shipping method.
     *
     * @param Money $netSubtotal Subtotal after coupon discount
     * @param string $shippingMethod 'standard' | 'express' | 'pickup'
     * @param array|null $shippingAddress Normalized or raw shipping address containing division/district
     */
    public function calculate(
        Money $netSubtotal,
        string $shippingMethod = 'standard',
        ?array $shippingAddress = null
    ): Money {
        $currency = $netSubtotal->currency;

        if ($shippingMethod === 'pickup') {
            return Money::zero($currency);
        }

        $division = trim((string) ($shippingAddress['division'] ?? 'Dhaka'));
        $district = trim((string) ($shippingAddress['district'] ?? $shippingAddress['city'] ?? 'Dhaka'));

        $isInsideDhakaMetro = strcasecmp($division, 'Dhaka') === 0 && strcasecmp($district, 'Dhaka') === 0;
        $isDhakaSubArea     = strcasecmp($division, 'Dhaka') === 0 && strcasecmp($district, 'Dhaka') !== 0;

        if ($shippingMethod === 'express') {
            $expressFee = match (true) {
                $isInsideDhakaMetro => 15000, // 150.00 BDT
                $isDhakaSubArea     => 18000, // 180.00 BDT
                default             => 25000, // 250.00 BDT outside Dhaka division
            };

            return new Money($expressFee, $currency);
        }

        // Standard shipping: Free over 5,000.00 BDT (500,000 paisa)
        if ($netSubtotal->amount >= self::FREE_STANDARD_SHIPPING_THRESHOLD_PAISA) {
            return Money::zero($currency);
        }

        // If no explicit Bangladesh division/district was provided, default to 120.00 BDT (12000 paisa)
        if (empty($shippingAddress['division'])) {
            return new Money(12000, $currency);
        }

        $standardFee = match (true) {
            $isInsideDhakaMetro => 7000,  // 70.00 BDT inside Dhaka District
            $isDhakaSubArea     => 10000, // 100.00 BDT Dhaka sub-area (Gazipur, Narayanganj, etc.)
            default             => 13000, // 130.00 BDT outside Dhaka Division (Chattogram, Sylhet, etc.)
        };

        return new Money($standardFee, $currency);
    }
}

