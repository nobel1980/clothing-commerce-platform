<?php

namespace App\Shared\Exceptions\Checkout;

class QuoteExpiredException extends CheckoutException
{
    public function __construct(string $quoteToken = '', string $message = '')
    {
        $msg = $message !== ''
            ? $message
            : 'The checkout quote has expired. Please generate a new quote before placing your order.';

        parent::__construct($msg, 422, 'quote_token', ['quote_token' => $quoteToken]);
    }
}

