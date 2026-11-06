<?php

namespace App\Console\Commands;

use App\Models\SiteSetting;
use App\Models\User;
use App\Models\VideoAsset;
use App\Notifications\EngagementNotification;
use App\Support\AcademySettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Number;

/** Warns admins once when stored video passes the alert threshold of the admin-set storage budget. */
class CheckStorageUsage extends Command
{
    protected $signature = 'academy:check-storage';

    protected $description = 'Notify admins when video storage passes the alert threshold';

    public function handle(): int
    {
        $settings = AcademySettings::all();
        $limit = max(1, (int) $settings['storage_limit_gb']) * 1024 ** 3;
        $used = (int) VideoAsset::where('status', 'ready')->sum('expected_bytes');
        $percent = (int) floor($used / $limit * 100);
        $state = SiteSetting::firstOrNew(['key' => 'storage_alert_state']);
        $alerted = (bool) ($state->value['alerted'] ?? false);

        if ($percent >= (int) $settings['storage_alert_percent'] && ! $alerted) {
            $admins = User::where('role', 'admin')->where('status', 'active')->get();
            Notification::send($admins, new EngagementNotification([
                'title' => __('Video storage at :percent%', ['percent' => $percent]),
                'body' => __(':used of your :limit storage budget is used. Remove unused videos or raise the budget in Site settings.', ['used' => Number::fileSize($used, 1), 'limit' => Number::fileSize($limit)]),
                'url' => '/admin/settings',
            ]));
            $state->value = ['alerted' => true, 'at' => now()->toIso8601String()];
            $state->save();
        } elseif ($percent < (int) $settings['storage_alert_percent'] && $alerted) {
            // Usage dropped back below the threshold: re-arm so the next crossing alerts again.
            $state->value = ['alerted' => false];
            $state->save();
        }
        $this->info("Video storage: {$percent}% of budget used.");

        return self::SUCCESS;
    }
}
