<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class ExportCanonicalSnapshot extends Command
{
    protected $signature = 'db:export-canonical
                            {--verify-emails : Mark all users email_verified_at before export}
                            {--output= : Optional absolute/relative output path}';

    protected $description = 'Export current MySQL data as the canonical production snapshot SQL';

    public function handle(): int
    {
        $output = $this->option('output')
            ?: database_path('seeders/data/canonical_snapshot.sql');

        $dir = dirname($output);
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            $this->error("Cannot create directory: {$dir}");

            return self::FAILURE;
        }

        if ($this->option('verify-emails')) {
            $updated = \App\Models\User::query()
                ->whereNull('email_verified_at')
                ->update(['email_verified_at' => now()]);
            $this->info("Verified emails for {$updated} user(s).");
        }

        $host = (string) config('database.connections.mysql.host');
        $port = (string) config('database.connections.mysql.port', 3306);
        $database = (string) config('database.connections.mysql.database');
        $username = (string) config('database.connections.mysql.username');
        $password = (string) config('database.connections.mysql.password');

        $ignore = [
            'cache',
            'cache_locks',
            'sessions',
            'jobs',
            'job_batches',
            'failed_jobs',
            'personal_access_tokens',
            'password_reset_tokens',
        ];

        $dumpBinary = $this->resolveDumpBinary();
        if ($dumpBinary === null) {
            $this->error('mysqldump/mariadb-dump not found in PATH. Run dump from host:');
            $this->line('  docker exec lms_mysql mysqldump -uroot -proot lms_db ... > database/seeders/data/canonical_snapshot.sql');

            return self::FAILURE;
        }

        $args = [
            $dumpBinary,
            '-h'.$host,
            '-P'.$port,
            '-u'.$username,
            '-p'.$password,
            '--single-transaction',
            '--routines',
            '--triggers',
            '--complete-insert',
            '--hex-blob',
            '--default-character-set=utf8mb4',
            '--no-tablespaces',
        ];

        foreach ($ignore as $table) {
            $args[] = '--ignore-table='.$database.'.'.$table;
        }

        $args[] = $database;

        $this->info("Running {$dumpBinary} against {$host}/{$database}...");

        $process = new Process($args, null, null, null, 300);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error($process->getErrorOutput() ?: $process->getOutput());

            return self::FAILURE;
        }

        file_put_contents($output, $process->getOutput());
        $bytes = is_file($output) ? filesize($output) : 0;
        $this->info(sprintf('Wrote %s (%.2f MB)', $output, $bytes / 1048576));

        return self::SUCCESS;
    }

    private function resolveDumpBinary(): ?string
    {
        foreach (['mysqldump', 'mariadb-dump'] as $bin) {
            $process = Process::fromShellCommandline(
                PHP_OS_FAMILY === 'Windows' ? "where {$bin}" : "command -v {$bin}"
            );
            $process->run();
            if ($process->isSuccessful() && trim($process->getOutput()) !== '') {
                return $bin;
            }
        }

        return null;
    }
}
