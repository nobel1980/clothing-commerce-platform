<?php

namespace App\Shared\Exceptions\Checkout;

class PriceChangedException extends CheckoutException
{
    public function __construct(
        int $variantId,
        int $expectedAmount,
        int $currentAmount,
        string $message = ''
    ) {
        $msg = $message !== ''
            ? $message
            : "Price for variant #{$variantId} has changed from {$expectedAmount} to {$currentAmount} paisa. Please refresh your checkout quote.";

        parent::__construct($msg, 409, 'price', [
            'variant_id'      => $variantId,
            'expected_amount' => $expectedAmount,
            'current_amount'  => $currentAmount,
        ]);
    }
}

