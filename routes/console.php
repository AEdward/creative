<?php

use App\Services\Media\MediaStorage;
use App\Support\BackgroundHealth;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('academy:expire-orders')->hourly()->withoutOverlapping();
Schedule::command('academy:prune-video-uploads')->hourly()->withoutOverlapping()->when(fn () => app(MediaStorage::class)->available());
Schedule::command('academy:check-storage')->dailyAt('06:00')->withoutOverlapping();
Schedule::command('academy:backup')->dailyAt('02:30')->withoutOverlapping()->onFailure(fn () => logger()->error('Scheduled database backup failed.'));
Schedule::call(fn () => BackgroundHealth::beat())->everyMinute()->name('scheduler-heartbeat');
Schedule::command('academy:health-alerts')->everyFifteenMinutes()->withoutOverlapping();
