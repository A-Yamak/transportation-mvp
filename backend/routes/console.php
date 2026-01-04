<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Commands
|--------------------------------------------------------------------------
|
| Here you may define all of your scheduled commands. Laravel provides
| a fluent API for defining your schedule with console commands.
|
*/

// Prune old Telescope entries (default: 48 hours)
Schedule::command('telescope:prune --hours=' . config('dashboards.telescope.prune_hours', 48))
    ->daily()
    ->runInBackground()
    ->withoutOverlapping();

// Horizon metrics snapshot for dashboard graphs
Schedule::command('horizon:snapshot')
    ->everyFiveMinutes()
    ->runInBackground();
