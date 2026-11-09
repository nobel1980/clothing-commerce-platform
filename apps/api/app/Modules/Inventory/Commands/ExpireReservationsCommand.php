<?php

namespace App\Modules\Inventory\Commands;

use App\Shared\Contracts\Inventory\InventoryServiceContract;
use Illuminate\Console\Command;

class ExpireReservationsCommand extends Command
{
    protected $signature = 'inventory:expire-reservations';

    protected $description = 'Expire overdue stock reservations and release their reserved stock back to available inventory';

    public function handle(InventoryServiceContract $inventoryService): int
    {
        $count = $inventoryService->expireOverdueReservations();

        $this->info("Expired {$count} overdue stock reservation(s).");

        return self::SUCCESS;
    }
}

