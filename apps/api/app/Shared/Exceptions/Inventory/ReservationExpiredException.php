<?php

namespace App\Shared\Exceptions\Inventory;

class ReservationExpiredException extends InvalidReservationStateException
{
    public function __construct(string $message = 'Cannot commit an expired stock reservation.')
    {
        parent::__construct($message);
    }
}

