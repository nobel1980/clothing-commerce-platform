<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessOrderTestJob implements ShouldQueue
{
    use Queueable;

    public string $orderRef;

    /**
     * Create a new job instance.
     */
    public function __construct(string $orderRef)
    {
        $this->orderRef = $orderRef;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("ProcessOrderTestJob handled successfully for orderRef: {$this->orderRef}");
    }
}
