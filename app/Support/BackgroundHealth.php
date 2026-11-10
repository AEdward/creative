<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Order expiry, backups, storage alerts and every notification depend on cron (schedule:run) and a queue
 * worker. The scheduler stamps a heartbeat each minute; the admin dashboard warns when either looks stuck.
 */
class BackgroundHealth
{
    public const HEARTBEAT_KEY = 'scheduler:heartbeat';

    public static function beat(): void
    {
        Cache::forever(self::HEARTBEAT_KEY, now()->timestamp);
    }

    /** @return list<string> human-readable problems; empty when healthy */
    public static function problems(): array
    {
        $problems = [];
        $last = Cache::get(self::HEARTBEAT_KEY);
        if (! $last) {
            $problems[] = 'Scheduled jobs have never run. Add the cron entry from docs/OPERATIONS.md (php artisan schedule:run every minute).';
        } elseif (now()->timestamp - (int) $last > 10 * 60) {
            $problems[] = 'Scheduled jobs last ran '.now()->createFromTimestamp((int) $last)->diffForHumans().'. Check the cron entry: unpaid orders are not expiring and backups are not being taken.';
        }
        if (config('queue.default') === 'database' && Schema::hasTable('jobs')) {
            $stuck = DB::table('jobs')->where('available_at', '<', now()->subMinutes(15)->timestamp)->count();
            if ($stuck > 0) {
                $problems[] = $stuck.' queued job(s) have waited over 15 minutes, so emails and notifications are not going out. Start the queue worker (php artisan queue:work).';
            }
        }

        return $problems;
    }
}
