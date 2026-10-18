<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('showcase:purge-requests')->daily();

Schedule::command('push:new-content')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('push:daily-reminders')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('push:exam-open')->everyFifteenMinutes()->withoutOverlapping();
