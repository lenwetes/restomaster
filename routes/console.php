<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('restomaster:backup')
    ->dailyAt('03:00')
    ->withoutOverlapping(120)
    ->onOneServer()
    ->runInBackground();

Schedule::command('restomaster:backup-storage')
    ->dailyAt('03:30')
    ->withoutOverlapping(120)
    ->onOneServer()
    ->runInBackground();
