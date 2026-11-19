<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessOrderTestJob implements ShouldQueue
{
    use Queueable;

    /**
     * Maximum number of attempts before the job is marked as failed.
     */
    public int $tries = 3;

    /**
     * Maximum number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * Exponential backoff intervals (in seconds) between retry attempts.
     *
     * @var list<int>
     */
    public array $backoff = [10, 30, 60];

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

    /**
     * Handle a permanent job failure after exhausting retries.
     */
    public function failed(Throwable $exception): void
    {
        Log::error("ProcessOrderTestJob failed permanently for orderRef: {$this->orderRef}", [
            'error' => $exception->getMessage(),
        ]);
    }
}
