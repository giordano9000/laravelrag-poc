<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule automatic source synchronization
Schedule::command('sources:sync')
    ->everyMinute()
    ->name('Auto-sync source connections')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();
