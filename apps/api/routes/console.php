<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Log;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('test:dispatch-job', function () {
    \App\Jobs\ProcessOrderTestJob::dispatch('test-order-' . time());
    $this->info('Dispatched ProcessOrderTestJob successfully.');
})->purpose('Dispatch a test job to the queue');

// Scheduled Tasks
Schedule::call(function () {
    Log::info('[Scheduler] Heartbeat scheduled task executed successfully.');
})->everyMinute()->name('heartbeat-scheduler');

Schedule::command('inventory:expire-reservations')
    ->everyMinute()
    ->name('inventory-expire-reservations')
    ->withoutOverlapping();

Schedule::command('queue:prune-failed --hours=48')->daily();
