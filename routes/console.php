<?php

use App\Console\Commands\SendAbandonedCartRemindersCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Hourly rather than by the minute: the window a cart has to sit idle before
// it counts as abandoned is an hour by default, so checking more often just
// runs the same query for nothing. withoutOverlapping() keeps a slow run (a
// large batch, a struggling mail queue) from being joined by the next one.
Schedule::command(SendAbandonedCartRemindersCommand::class)
    ->hourly()
    ->withoutOverlapping();
