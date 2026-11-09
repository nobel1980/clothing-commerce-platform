<?php

namespace App\Modules\Orders\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;

class OrderPolicy
{
    public function view(?User $user, Order $order, ?string $guestToken = null): bool
    {
        if ($user !== null) {
            if ($user->is_staff || ($user->exists && $user->hasRole('super_admin'))) {
                return true;
            }

            if ($order->user_id !== null && (int) $order->user_id === (int) $user->id) {
                return true;
            }
        }

        if ($order->user_id === null && $order->guest_token !== null && $guestToken !== null) {
            return hash_equals((string) $order->guest_token, trim($guestToken));
        }

        return false;
    }
}
