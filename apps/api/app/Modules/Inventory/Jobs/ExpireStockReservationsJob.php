<?php

namespace App\Modules\Inventory\Jobs;

use App\Shared\Contracts\Inventory\InventoryServiceContract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ExpireStockReservationsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 3600;

    public function handle(InventoryServiceContract $inventoryService): void
    {
        $expiredCount = $inventoryService->expireOverdueReservations();

        if ($expiredCount > 0) {
            Log::info("[Inventory] Expired {$expiredCount} overdue stock reservation(s).");
        }
    }
}

