<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Default seeder = canonical production snapshot (data hiện tại đã chốt).
 *
 * - Chuẩn prod: php artisan migrate:fresh --seed
 * - Demo generator cũ: php artisan db:seed --class=DemoDatabaseSeeder
 * - Xuất lại snapshot: php artisan db:export-canonical
 *
 * Override: SEED_MODE=demo trong .env để dùng DemoDatabaseSeeder.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $mode = strtolower((string) env('SEED_MODE', 'canonical'));

        if ($mode === 'demo') {
            $this->call(DemoDatabaseSeeder::class);

            return;
        }

        $this->call(CanonicalSnapshotSeeder::class);
        $this->call(VoucherSeeder::class);
    }
}
