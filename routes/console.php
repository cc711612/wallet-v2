<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('command:daily_update_exchange_rate')->hourly();
// 每月 1 日台灣時間 00:00 開新帳本、結算上月（app.timezone 維持 UTC，DB 時間戳記不受影響）
Schedule::command('command:auto_create_wallet')->monthlyOn(1, '00:00')->timezone(config('app.business_timezone'));
if (config('app.env') === 'production') {
    Schedule::command('command:auto_calculate_wallet')->monthlyOn(1, '00:00')->timezone(config('app.business_timezone'));
}
Schedule::command('telescope:prune --hours=168')->daily();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
