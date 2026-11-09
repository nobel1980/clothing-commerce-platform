<?php

namespace App\Modules\Inventory\Support;

use App\Shared\Exceptions\Inventory\UnauthorizedInventoryMutationException;

final class InventoryWriteGuard
{
    private static int $authorizedDepth = 0;

    /**
     * Execute a callback within an authorized Inventory stock mutation context.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function authorizeStockMutation(callable $callback): mixed
    {
        self::$authorizedDepth++;

        try {
            return $callback();
        } finally {
            self::$authorizedDepth--;
        }
    }

    public static function isStockMutationAuthorized(): bool
    {
        return self::$authorizedDepth > 0;
    }

    /**
     * Ensure that external domain modules (such as Catalog or Orders) are not
     * directly mutating Inventory models bypassing the Inventory module services.
     */
    public static function assertModuleIsolation(): void
    {
        if (self::$authorizedDepth > 0) {
            return;
        }

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 25);

        $enteredViaInventoryModule = false;
        $forbiddenNamespaces = [
            'App\\Modules\\Catalog\\',
            'App\\Modules\\Orders\\',
            'App\\Modules\\Cart\\',
            'App\\Modules\\Checkout\\',
            'App\\Modules\\Payments\\',
            'App\\Modules\\Promotions\\',
            'App\\Modules\\Shipping\\',
        ];

        foreach ($trace as $frame) {
            $class = $frame['class'] ?? '';

            if (
                str_starts_with($class, 'App\\Modules\\Inventory\\Services\\') ||
                str_starts_with($class, 'App\\Modules\\Inventory\\Actions\\') ||
                str_starts_with($class, 'Database\\Factories\\Inventory\\') ||
                str_starts_with($class, 'Database\\Seeders\\')
            ) {
                $enteredViaInventoryModule = true;
                break;
            }

            foreach ($forbiddenNamespaces as $forbidden) {
                if (str_starts_with($class, $forbidden)) {
                    throw new UnauthorizedInventoryMutationException(
                        "Module isolation violation: {$class} attempted to directly modify an Inventory table. Use InventoryServiceContract instead."
                    );
                }
            }
        }

        if ($enteredViaInventoryModule) {
            return;
        }
    }
}

