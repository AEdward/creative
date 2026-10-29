<?php

use App\Services\Media\MediaStorage;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('academy:expire-orders')->hourly()->withoutOverlapping();
Schedule::command('academy:prune-video-uploads')->hourly()->withoutOverlapping()->when(fn () => app(MediaStorage::class)->available());
