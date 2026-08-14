<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Demo/dev seed pipeline (synthetic data generators).
 *
 * Production / baseline data uses DatabaseSeeder → CanonicalSnapshotSeeder.
 * Run explicitly when you need to rebuild from factories/scripts:
 *   php artisan db:seed --class=DemoDatabaseSeeder
 */
class DemoDatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            SiteSettingsSeeder::class,
            CategorySeeder::class,
            UserSeeder::class,
            OrgAcademicSeeder::class,
            CourseSeeder::class,
            CourseContentSeeder::class,
            QuestionBankSeeder::class,
            TrainingProgramSeeder::class,
            MarketplaceSeeder::class,
            CareerSeeder::class,
            NotificationSeeder::class,
            CertificateTemplateSeeder::class,
            CareerPathSeeder::class,
            CourseFrameworkSeeder::class,
            AcademicDemoDataSeeder::class,
            NewsSeeder::class,
        ]);
    }
}
