<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Master orchestrator — thứ tự quan trọng:
 *  1. Roles & permissions
 *  2. Site settings
 *  3. Categories
 *  4. Users (admin / instructor / student)
 *  5. Cấu trúc tổ chức + học vụ (institution, program, cohort, admin class)
 *  6. Courses (core + extension, thumbnail, video_url)
 *  7. Nội dung khóa học (sections, lessons, quiz, assignment…)
 *  8. Ngân hàng câu hỏi (bổ sung thêm loại câu hỏi)
 *  9. Chương trình đào tạo từ JSON
 * 10. Ghi danh học thuật + bảng điểm
 * 11. Marketplace (order, enrollment, review, progress)
 * 12. Career (job posting, CV, recommendation)
 * 13. Notifications
 * 14. Certificate templates + gán cho courses
 * 15. Career paths (lộ trình nghề marketplace)
 */
class DatabaseSeeder extends Seeder
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
            AcademicSeeder::class,
            MarketplaceSeeder::class,
            CareerSeeder::class,
            NotificationSeeder::class,
            CertificateTemplateSeeder::class,
            CareerPathSeeder::class,
        ]);
    }
}
