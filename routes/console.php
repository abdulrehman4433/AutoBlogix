<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * One `php artisan schedule:run` cron line drives the whole publish loop:
 * the scheduler dispatches due posts and recovers stuck attempts, and the
 * queue worker drains what was dispatched. `withoutOverlapping(5)` bounds
 * stale mutexes (a crashed run cannot block the loop for hours). In dev,
 * run `php artisan schedule:work` instead of cron.
 */
Schedule::command('posts:run-scheduler')
    ->everyMinute()
    ->withoutOverlapping(5);

Schedule::command('queue:work --stop-when-empty')
    ->everyMinute()
    ->withoutOverlapping(5);
