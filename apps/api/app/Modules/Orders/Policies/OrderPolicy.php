<?php

namespace App\Modules\Orders\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;

class OrderPolicy
{
    /**
     * Determine whether the user can view the order.
     */
    public function view(?User $user, Order $order): bool
    {
        if ($user === null) {
            return false;
        }

        if ($this->isStaffOrAdmin($user)) {
            return true;
        }

        return $order->user_id !== null && (int) $order->user_id === (int) $user->id;
    }

    /**
     * Determine whether the user can cancel the order.
     */
    public function cancel(?User $user, Order $order): bool
    {
        return $this->view($user, $order);
    }

    /**
     * Determine whether the user can manage the order in admin backoffice.
     */
    public function manage(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $this->isStaffOrAdmin($user);
    }

    private const STAFF_ROLES = [
        'super_admin',
        'product_manager',
        'inventory_manager',
        'order_manager',
        'customer_support',
        'marketing_manager',
        'report_viewer',
    ];

    private function isStaffOrAdmin(User $user): bool
    {
        // Persisted users: authoritative check via Spatie role assignments.
        if ($user->exists) {
            return $user->isStaff();
        }

        // Unpersisted users (e.g. unit tests): fall back to the `role` column.
        return in_array((string) ($user->role ?? ''), self::STAFF_ROLES, true);
    }
}
