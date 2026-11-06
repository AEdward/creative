<?php

namespace App\Support;

use App\Services\Media\MediaStorage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

/**
 * One list of checks used by the Site health page and the 15-minute alert job.
 * Each check: ['key', 'label', 'status' => ok|warn|fail, 'detail'].
 */
class SiteHealth
{
    /** @return list<array{key: string, label: string, status: string, detail: string}> */
    public function checks(): array
    {
        return array_values(array_filter([
            $this->database(), $this->disk(), $this->scheduler(), $this->queue(), $this->failedJobs(),
            $this->backups(), $this->mail(), $this->media(), $this->security(), $this->errors(),
        ]));
    }

    public function problems(): array
    {
        return array_values(array_filter($this->checks(), fn ($c) => $c['status'] !== 'ok'));
    }

    private function check(string $key, string $label, string $status, string $detail): array
    {
        return compact('key', 'label', 'status', 'detail');
    }

    private function database(): array
    {
        try {
            $version = DB::connection()->getDriverName() === 'sqlite' ? 'SQLite' : (string) (DB::selectOne('select version() as v')->v ?? '');

            return $this->check('database', 'Database', 'ok', 'Connected · '.$version);
        } catch (\Throwable $e) {
            return $this->check('database', 'Database', 'fail', 'Cannot connect: '.class_basename($e));
        }
    }

    /**
     * Disk space for this hosting account. On cPanel the plan quota comes from `uapi Quota` (cached for
     * an hour); a limit of 0 means unlimited. The shared server's own disk only matters if it is nearly full.
     */
    private function disk(): array
    {
        $writable = is_writable(storage_path('framework')) && is_writable(storage_path('logs'));
        if (! $writable) {
            return $this->check('disk', 'Disk space', 'fail', 'The storage folder is not writable.');
        }
        $free = @disk_free_space(storage_path());
        $total = @disk_total_space(storage_path());
        $serverPercentFree = $free !== false && $total ? $free / $total * 100 : 100;
        if ($serverPercentFree < 3) {
            return $this->check('disk', 'Disk space', 'fail', 'The shared hosting server is almost full ('.Number::fileSize((float) $free, 1).' left). Contact Yegara.');
        }

        $quota = $this->accountQuota();
        if ($quota === null) {
            return $this->check('disk', 'Disk space', 'ok', 'Writable · account usage not available here');
        }
        [$usedMb, $limitMb] = $quota;
        $used = Number::fileSize($usedMb * 1024 * 1024, 1);
        if ($limitMb <= 0) {
            return $this->check('disk', 'Disk space', 'ok', $used.' used · plan has no fixed limit');
        }
        $percent = $usedMb / $limitMb * 100;

        return $this->check('disk', 'Disk space', $percent >= 95 ? 'fail' : ($percent >= 85 ? 'warn' : 'ok'), $used.' used of '.Number::fileSize($limitMb * 1024 * 1024, 1).' ('.round($percent).'%)');
    }

    /** [usedMb, limitMb] from cPanel, or null when not on cPanel / shell commands are disabled. */
    private function accountQuota(): ?array
    {
        return Cache::remember('health:account-quota', now()->addHour(), function (): ?array {
            if (! function_exists('shell_exec') || in_array('shell_exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true)) {
                return null;
            }
            $json = @shell_exec('uapi --output=json Quota get_quota_info 2>/dev/null');
            $data = is_string($json) ? json_decode($json, true) : null;
            $info = $data['result']['data'] ?? null;
            if (! is_array($info) || ! isset($info['megabytes_used'])) {
                return null;
            }

            return [(float) $info['megabytes_used'], (float) ($info['megabyte_limit'] ?? 0)];
        });
    }

    private function scheduler(): array
    {
        $last = Cache::get(BackgroundHealth::HEARTBEAT_KEY);
        if (! $last) {
            return $this->check('scheduler', 'Scheduled jobs', 'fail', 'Have never run. Add the schedule:run cron job.');
        }
        $minutes = (int) floor((now()->timestamp - (int) $last) / 60);

        return $this->check('scheduler', 'Scheduled jobs', $minutes > 10 ? 'fail' : 'ok', $minutes < 1 ? 'Ran less than a minute ago' : "Last ran {$minutes} min ago");
    }

    private function queue(): array
    {
        if (config('queue.default') !== 'database') {
            return $this->check('queue', 'Email & notification queue', 'ok', 'Sent immediately ('.config('queue.default').')');
        }
        $waiting = DB::table('jobs')->count();
        $stuck = DB::table('jobs')->where('available_at', '<', now()->subMinutes(15)->timestamp)->count();

        return $this->check('queue', 'Email & notification queue', $stuck > 0 ? 'fail' : 'ok', $stuck > 0 ? "{$stuck} message(s) waiting over 15 minutes — is the queue cron running?" : "{$waiting} waiting");
    }

    private function failedJobs(): array
    {
        $failed = DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();

        return $this->check('failed', 'Failed emails / jobs (24 h)', $failed > 0 ? 'warn' : 'ok', $failed > 0 ? "{$failed} failed. Check the email settings, then retry with: php artisan queue:retry all" : 'None');
    }

    private function backups(): array
    {
        if (config('database.default') !== 'mysql' && config('database.default') !== 'mariadb') {
            return $this->check('backups', 'Database backup', 'ok', 'Not used on this database type');
        }
        $files = collect(File::glob(storage_path('app/backups/db-*.sql.gz')))->sort()->values();
        if ($files->isEmpty()) {
            return $this->check('backups', 'Database backup', 'warn', 'No backup yet. The first one runs at 02:30.');
        }
        $latest = $files->last();
        $hours = (int) floor((time() - filemtime($latest)) / 3600);

        return $this->check('backups', 'Database backup', $hours > 36 ? 'fail' : 'ok', 'Last: '.basename($latest).' ('.Number::fileSize(filesize($latest), 1).", {$hours} h ago) · {$files->count()} kept on this server");
    }

    private function mail(): array
    {
        $mailer = (string) config('mail.default');
        if (in_array($mailer, ['log', 'array'], true)) {
            return $this->check('mail', 'Email sending', app()->isProduction() ? 'fail' : 'warn', "Emails are not sent (mailer: {$mailer}).");
        }

        return $this->check('mail', 'Email sending', 'ok', 'SMTP · '.config('mail.mailers.smtp.host').' · from '.config('mail.from.address'));
    }

    private function media(): array
    {
        return app(MediaStorage::class)->available()
            ? $this->check('media', 'Video storage (Cloudflare R2)', 'ok', 'Configured · bucket '.config('filesystems.disks.r2.bucket'))
            : $this->check('media', 'Video storage (Cloudflare R2)', 'warn', 'Not connected yet: video uploads and playback are off.');
    }

    private function security(): array
    {
        $issues = [];
        if (config('app.debug')) {
            $issues[] = 'debug mode is on';
        }
        if (! str_starts_with((string) config('app.url'), 'https://') && app()->isProduction()) {
            $issues[] = 'APP_URL is not https';
        }

        return $issues
            ? $this->check('security', 'Security settings', 'fail', ucfirst(implode(', ', $issues)).'.')
            : $this->check('security', 'Security settings', 'ok', 'Debug off'.(app()->isProduction() ? ', HTTPS' : ''));
    }

    private function errors(): array
    {
        $file = storage_path('logs/laravel-'.now()->format('Y-m-d').'.log');
        if (! is_file($file)) {
            $file = storage_path('logs/laravel.log');
        }
        $count = 0;
        if (is_file($file) && filesize($file) < 50 * 1024 * 1024) {
            $today = '['.now()->format('Y-m-d');
            foreach (new \SplFileObject($file) as $line) {
                if (str_starts_with((string) $line, $today) && preg_match('/\.(ERROR|CRITICAL|ALERT|EMERGENCY):/', (string) $line)) {
                    $count++;
                }
            }
        }

        return $this->check('errors', 'Errors logged today', $count >= 20 ? 'fail' : ($count > 0 ? 'warn' : 'ok'), $count ? "{$count} error(s) in storage/logs" : 'None');
    }

    /** Live check that the R2 bucket answers (button on the page; not run on every load). */
    public function pingMedia(): array
    {
        if (! app(MediaStorage::class)->available()) {
            return $this->media();
        }
        try {
            Storage::disk('r2')->exists('health-check/ping');

            return $this->check('media', 'Video storage (Cloudflare R2)', 'ok', 'Bucket answered just now · '.config('filesystems.disks.r2.bucket'));
        } catch (\Throwable $e) {
            return $this->check('media', 'Video storage (Cloudflare R2)', 'fail', 'Bucket did not answer: '.class_basename($e).'. Check the R2 keys and endpoint in .env.');
        }
    }
}
