<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command(
    'imagepilot:process-batches',
    [
        '--limit' => config('imagepilot.worker.limit'),
    ]
)
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command(
    'imagepilot:cleanup-batches'
)
    ->daily()
    ->withoutOverlapping();


Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('test-image-processing', function () {
    
});
