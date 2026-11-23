<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('funding:process')->everyMinute()->withoutOverlapping();
foreach (['shops:scan-documents', 'shops:scan-documents --cases'] as $scan) {
    Schedule::command($scan)->everyFiveMinutes()->withoutOverlapping()
        ->when(fn () => filled(config('filesystems.shop_clamscan_binary')))
        ->runInBackground();
}

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
