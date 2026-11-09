<?php

namespace App\Shared\Events\Checkout;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CartMerged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $guestCartId,
        public readonly int $customerCartId,
        public readonly int $userId,
    ) {}
}

