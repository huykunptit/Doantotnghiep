<?php

namespace Database\Seeders;

use App\Models\CareerRecommendation;
use App\Models\Course;
use App\Models\JobPosting;
use App\Models\User;
use App\Models\UserCV;
use Illuminate\Database\Seeder;

/**
 * Seed dữ liệu career:
 *  - 2 job postings mẫu
 *  - 4 student đầu: CV + CareerRecommendation
 *
 * Chạy độc lập: php artisan db:seed --class=CareerSeeder
 */
class CareerSeeder extends Seeder
{
    public function run(): void
    {
        $jobs = $this->seedJobPostings();
        $this->seedStudentCareerData($jobs);
    }

    private function seedJobPostings(): \Illuminate\Support\Collection
    {
        $definitions = [
            [
                'title'            => 'Junior Backend Laravel Developer',
                'company'          => 'PTIT Digital Lab',
                'description'      => 'Tham gia xây dựng API và tích hợp dịch vụ cho hệ thống đào tạo số.',
                'required_skills'  => ['PHP', 'Laravel', 'MySQL', 'REST API'],
                'location'         => 'Hà Nội',
            ],
            [
                'title'            => 'Frontend Nuxt Engineer',
                'company'          => 'Edu Product Studio',
                'description'      => 'Phát triển giao diện học tập tối ưu cho desktop và mobile.',
                'required_skills'  => ['Nuxt', 'Vue', 'Tailwind CSS', 'TypeScript'],
                'location'         => 'Remote',
            ],
            [
                'title'            => 'DevOps / Cloud Engineer',
                'company'          => 'EduCloud VN',
                'description'      => 'Quản trị hạ tầng, CI/CD pipeline và triển khai container cho nền tảng LMS.',
                'required_skills'  => ['Docker', 'Kubernetes', 'CI/CD', 'Linux'],
                'location'         => 'Hà Nội',
            ],
            [
                'title'            => 'Mobile Developer (Flutter)',
                'company'          => 'PTIT Mobile Team',
                'description'      => 'Phát triển ứng dụng học tập di động đa nền tảng cho sinh viên PTIT.',
                'required_skills'  => ['Flutter', 'Dart', 'REST API', 'Firebase'],
                'location'         => 'Hà Nội',
            ],
        ];

        return collect($definitions)->map(fn($payload) =>
            JobPosting::query()->updateOrCreate(
                ['title' => $payload['title'], 'company' => $payload['company']],
                $payload
            )
        );
    }

    private function seedStudentCareerData(\Illuminate\Support\Collection $jobs): void
    {
        $students = UserSeeder::getStudents();

        $extensionCourses = Course::query()
            ->where('course_mode', 'extension')
            ->where('status', 'published')
            ->orderBy('id')
            ->limit(4)
            ->get();

        $students->take(4)->each(function (User $student, int $index) use ($jobs, $extensionCourses) {
            UserCV::query()->updateOrCreate(
                ['user_id' => $student->id, 'file_name' => "cv-demo-{$student->id}.pdf"],
                [
                    'file_path'   => "career/cvs/cv-demo-{$student->id}.pdf",
                    'parsed_text' => 'Laravel, Nuxt, REST API, SQL, teamwork, presentation',
                    'skills'      => ['Laravel', 'Nuxt', 'REST API', 'SQL', 'Communication'],
                ]
            );

            $job = $jobs[$index % $jobs->count()];

            CareerRecommendation::query()->updateOrCreate(
                ['user_id' => $student->id, 'job_id' => $job->id],
                [
                    'match_score'      => 78 + ($index * 4),
                    'skill_gaps'       => ['Docker', 'Testing'],
                    'suggested_courses'=> $extensionCourses->pluck('id')->take(2)->values()->all(),
                    'ai_summary'       => 'Ứng viên có nền tảng phù hợp, nên bổ sung thêm kỹ năng triển khai và kiểm thử.',
                ]
            );
        });

        $this->command?->info('CareerSeeder: job postings và career data đã được seed.');
    }
}

