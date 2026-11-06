<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Nightly MySQL dump to storage/app/backups (gzip), keeping the newest N files.
 * Copy that folder off the server too (cPanel backup, rclone to R2, etc.) — see docs/OPERATIONS.md.
 */
class BackupDatabase extends Command
{
    protected $signature = 'academy:backup {--keep=14 : How many daily backups to keep}';

    protected $description = 'Dump the MySQL database to a compressed backup file';

    public function handle(): int
    {
        $config = config('database.connections.'.config('database.default'));
        if (($config['driver'] ?? null) !== 'mysql' && ($config['driver'] ?? null) !== 'mariadb') {
            $this->error('Backups support MySQL/MariaDB only.');

            return self::FAILURE;
        }
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);
        $file = $dir.'/db-'.now()->format('Y-m-d-His').'.sql.gz';

        // The password goes through the environment, never the command line, so it can't leak via the process list.
        $process = new Process([(string) config('academy.mysqldump_path'), '--single-transaction', '--quick', '--routines', '--no-tablespaces',
            '-h', (string) $config['host'], '-P', (string) $config['port'], '-u', (string) $config['username'], (string) $config['database']],
            null, ['MYSQL_PWD' => (string) $config['password']], null, 3600);
        $out = gzopen($file, 'wb6');
        $process->run(function (string $type, string $buffer) use ($out): void {
            if ($type === Process::OUT) {
                gzwrite($out, $buffer);
            }
        });
        gzclose($out);

        if (! $process->isSuccessful()) {
            File::delete($file);
            $this->error('Backup failed: '.trim($process->getErrorOutput()));
            report(new \RuntimeException('Database backup failed: '.trim($process->getErrorOutput())));

            return self::FAILURE;
        }

        $old = collect(File::glob($dir.'/db-*.sql.gz'))->sort()->reverse()->slice(max(1, (int) $this->option('keep')));
        File::delete($old->all());
        $this->info('Backup written: '.basename($file).' ('.round(filesize($file) / 1024).' KB)');

        return self::SUCCESS;
    }
}
