<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('command:daily_update_exchange_rate')->hourly();
Schedule::command('command:auto_create_wallet')->monthlyOn(1, '00:00');
if (config('app.env') === 'production') {
    Schedule::command('command:auto_calculate_wallet')->monthlyOn(1, '00:00');
}

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
