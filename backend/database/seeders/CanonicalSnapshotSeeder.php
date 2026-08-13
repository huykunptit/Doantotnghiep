<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Nạp snapshot chuẩn từ DB hiện tại (canonical_snapshot.sql).
 *
 * File: database/seeders/data/canonical_snapshot.sql
 * Xuất lại: php artisan db:export-canonical
 *
 * Dùng sau migrate (hoặc migrate:fresh --seed). Dump gồm DROP/CREATE/INSERT
 * nên sẽ ghi đè schema+data theo bản chuẩn, trừ các bảng runtime bị bỏ qua
 * (cache, sessions, jobs, tokens...).
 */
class CanonicalSnapshotSeeder extends Seeder
{
    public const SNAPSHOT_RELATIVE = 'seeders/data/canonical_snapshot.sql';

    public function run(): void
    {
        $path = database_path(self::SNAPSHOT_RELATIVE);

        if (! is_file($path)) {
            throw new RuntimeException(
                "Missing canonical snapshot at {$path}. Run: php artisan db:export-canonical"
            );
        }

        $sql = file_get_contents($path);
        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException("Canonical snapshot is empty: {$path}");
        }

        // Strip UTF-8 BOM if present.
        if (str_starts_with($sql, "\xEF\xBB\xBF")) {
            $sql = substr($sql, 3);
        }

        // Avoid DEFINER privilege issues on shared hosts.
        $sql = preg_replace('/DEFINER=`[^`]+`@`[^`]+`/', 'DEFINER=CURRENT_USER', $sql) ?? $sql;

        $this->command?->info('Importing canonical snapshot (this may take a minute)...');

        $pdo = DB::connection()->getPdo();
        $previous = $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

        try {
            // Multi-statement import of the full dump.
            $pdo->exec($sql);
        } catch (Throwable $e) {
            throw new RuntimeException('Failed importing canonical snapshot: '.$e->getMessage(), 0, $e);
        } finally {
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $previous);
        }

        // Ensure runtime tables exist even if ignored by dump.
        foreach (['cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs', 'personal_access_tokens', 'password_reset_tokens'] as $table) {
            // no-op probe — migrations already created them on fresh installs
            if (! DB::getSchemaBuilder()->hasTable($table)) {
                $this->command?->warn("Runtime table missing after snapshot: {$table}");
            }
        }

        $users = (int) DB::table('users')->count();
        $verified = (int) DB::table('users')->whereNotNull('email_verified_at')->count();
        $courses = (int) DB::table('courses')->count();

        $this->command?->info("Canonical snapshot loaded: {$users} users ({$verified} verified), {$courses} courses.");
    }
}
