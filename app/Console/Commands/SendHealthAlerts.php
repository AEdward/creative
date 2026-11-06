<?php

namespace App\Console\Commands;

use App\Models\SiteSetting;
use App\Models\User;
use App\Notifications\EngagementNotification;
use App\Support\Admin;
use App\Support\SiteHealth;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Emails super admins when a health check newly fails (and when it recovers). Only "fail" checks
 * alert, and each change is sent once, so a problem that stays broken doesn't spam every 15 minutes.
 */
class SendHealthAlerts extends Command
{
    protected $signature = 'academy:health-alerts';

    protected $description = 'Notify super admins when site health checks start or stop failing';

    public function handle(SiteHealth $health): int
    {
        $failing = collect($health->checks())->where('status', 'fail')->keyBy('key');
        $state = SiteSetting::firstOrNew(['key' => 'health_alert_state']);
        $before = collect($state->value['failing'] ?? []);

        $new = $failing->keys()->diff($before);
        $fixed = $before->diff($failing->keys());
        if ($new->isNotEmpty() || $fixed->isNotEmpty()) {
            $lines = $new->map(fn ($key) => '✗ '.$failing[$key]['label'].': '.$failing[$key]['detail'])
                ->merge($fixed->map(fn ($key) => '✓ Fixed: '.$key));
            $supers = User::staffFor('super');
            if ($supers->isNotEmpty()) {
                Notification::send($supers, new EngagementNotification([
                    'title' => $new->isNotEmpty() ? __('Creative Lab: something needs attention') : __('Creative Lab: problem fixed'),
                    'body' => $lines->implode("\n"),
                    'url' => Admin::url('site-health'),
                ]));
            }
            $state->value = ['failing' => $failing->keys()->values()->all(), 'changed_at' => now()->toIso8601String()];
            $state->save();
        }
        $this->info($failing->isEmpty() ? 'All checks passing.' : 'Failing: '.$failing->keys()->implode(', '));

        return self::SUCCESS;
    }
}
