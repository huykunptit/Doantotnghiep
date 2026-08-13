<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\CourseQa;
use App\Models\CourseQaReply;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Khung catalog cho mọi khóa học:
 *  - Mô tả / outcomes / benefits / requirements / level / trailer
 *  - Curriculum skeleton (nếu chưa có lesson) qua CourseContentSeeder
 *  - Đánh giá + hỏi đáp mẫu (để trang chi tiết không trống)
 *
 * Chạy: php artisan db:seed --class=CourseFrameworkSeeder
 */
class CourseFrameworkSeeder extends Seeder
{
    public function run(): void
    {
        $courses = Course::query()
            ->with(['category.parent', 'instructor:id,name'])
            ->orderBy('id')
            ->get();

        if ($courses->isEmpty()) {
            $this->command?->warn('CourseFrameworkSeeder: chưa có khóa học.');
            return;
        }

        $students = UserSeeder::getStudents();
        if ($students->isEmpty()) {
            $students = User::query()
                ->where(function ($q) {
                    $q->where('user_type', 'student')
                        ->orWhereHas('roles', fn ($r) => $r->where('name', 'student'));
                })
                ->orderBy('id')
                ->limit(40)
                ->get();
        }

        $stats = [
            'copy' => 0,
            'reviews' => 0,
            'qas' => 0,
            'replies' => 0,
        ];

        foreach ($courses as $index => $course) {
            $this->seedCatalogCopy($course);
            $stats['copy']++;

            if ($students->isNotEmpty()) {
                $stats['reviews'] += $this->seedReviews($course, $students, $index);
                [$qaCount, $replyCount] = $this->seedQas($course, $students, $index);
                $stats['qas'] += $qaCount;
                $stats['replies'] += $replyCount;
            }
        }

        // Curriculum khung (sections/lessons/quiz…) cho khóa chưa có nội dung.
        $this->command?->info('CourseFrameworkSeeder: seeding curriculum skeleton via CourseContentSeeder…');
        $this->call(CourseContentSeeder::class);

        $this->command?->info(sprintf(
            'CourseFrameworkSeeder done — copy:%d reviews:%d qas:%d replies:%d',
            $stats['copy'],
            $stats['reviews'],
            $stats['qas'],
            $stats['replies'],
        ));
    }

    private function seedCatalogCopy(Course $course): void
    {
        $title = trim($course->title);
        $mode = $course->course_mode === 'core' ? 'core' : 'extension';
        $category = $course->category?->name ?: 'học phần PTIT';
        $level = $this->guessLevel($title);
        $modeLabel = $mode === 'core'
            ? 'học phần chính quy trong chương trình đào tạo'
            : 'khoá bổ trợ / marketplace (học linh hoạt, có thể mua lẻ hoặc theo lộ trình nghề)';

        $description = implode('', [
            "<p><strong>{$title}</strong> là {$modeLabel} thuộc nhóm <em>{$category}</em>.</p>",
            "<p>Trang này đang dùng <strong>khung nội dung demo</strong>: giới thiệu, mục tiêu, chương trình học mẫu, đánh giá và hỏi đáp. Bạn có thể chọn 2–3 khoá để thay bằng nội dung thật sau.</p>",
            '<p>Sau khi ghi danh, học viên đi theo lộ trình: tổng quan → kiến thức nền → thực hành / quiz → assignment &amp; workshop.</p>',
            '<ul>',
            '<li>Video / tài liệu khung kèm quiz kiểm tra nhanh</li>',
            '<li>Bài tập nộp file và buổi live/offline minh họa</li>',
            '<li>Hỏi đáp với giảng viên phụ trách khoá</li>',
            '</ul>',
        ]);

        $outcomes = [
            "Nắm được mục tiêu, phạm vi và thuật ngữ cốt lõi của «{$title}».",
            "Áp dụng được quy trình / kỹ thuật cơ bản qua ví dụ mẫu trong khoá.",
            'Tự kiểm tra kiến thức bằng quiz và hoàn thành assignment khung.',
            'Biết hướng mở rộng / học tiếp theo lộ trình nghề hoặc CTĐT liên quan.',
        ];

        $benefits = [
            'Chương trình học chia theo chương rõ ràng, dễ theo dõi tiến độ.',
            'Có quiz, bài tập và phiên Q&amp;A để luyện tập ngay trên LMS.',
            $mode === 'core'
                ? 'Gắn với học phần chính quy — phục vụ lớp tín chỉ / ghi danh theo CTĐT.'
                : 'Có thể mua lẻ hoặc kết hợp trong lộ trình nghề trên marketplace.',
            'Giảng viên phụ trách có thể cập nhật nội dung thật mà không đổi cấu trúc trang.',
        ];

        $requirements = [
            'Tài khoản LMS và thiết bị có trình duyệt hiện đại (Chrome/Edge/Firefox).',
            'Kiến thức nền tối thiểu đúng trình độ khoá (xem mức độ bên dưới).',
            $mode === 'core'
                ? 'Thuộc đúng chương trình / lớp hành chính được ghi danh học phần.'
                : 'Sẵn sàng thanh toán (nếu khoá có phí) hoặc đã được ghi danh demo.',
        ];

        $trailer = $this->guessTrailer($course);

        $course->forceFill([
            'description' => $description,
            'learning_outcomes' => $outcomes,
            'benefits' => $benefits,
            'requirements' => $requirements,
            'level' => $level,
            'trailer_url' => $course->trailer_url ?: $trailer,
            'status' => $course->status ?: 'published',
            'published_at' => $course->published_at ?: now()->subDays(14),
        ])->save();
    }

    private function seedReviews(Course $course, $students, int $courseIndex): int
    {
        $templates = [
            [5, "Nội dung «{$course->title}» trình bày rõ, phần tổng quan dễ tiếp cận."],
            [4, 'Khung bài học ổn: có video, quiz và assignment để luyện. Phần demo vẫn đang hoàn thiện.'],
            [5, 'Giảng viên phản hồi Q&A nhanh. Phù hợp để nắm roadmap trước khi học sâu.'],
            [4, 'Tài liệu đính kèm và quiz giúp ôn nhanh. Mong chờ bản nội dung đầy đủ hơn.'],
            [5, 'Cấu trúc chương hợp lý, phù hợp sinh viên mới tiếp cận môn này.'],
        ];

        $count = 0;
        $take = 3;
        for ($i = 0; $i < $take; $i++) {
            $student = $students[($courseIndex + $i * 3) % $students->count()];
            [$rating, $comment] = $templates[$i % count($templates)];

            Review::query()->updateOrCreate(
                [
                    'user_id' => $student->id,
                    'course_id' => $course->id,
                ],
                [
                    'rating' => $rating,
                    'comment' => $comment,
                    'created_at' => now()->subDays(8 - $i),
                    'updated_at' => now()->subDays(8 - $i),
                ]
            );
            $count++;
        }

        return $count;
    }

    /** @return array{0:int,1:int} */
    private function seedQas(Course $course, $students, int $courseIndex): array
    {
        $instructorId = $course->user_id;
        $qaBlueprints = [
            [
                'subject' => "Tài liệu bắt buộc cho «{$course->title}»?",
                'content' => "Em mới ghi danh khoá «{$course->title}». Thầy/cô cho em hỏi cần chuẩn bị giáo trình hoặc phần mềm gì trước khi học chương 1 ạ?",
                'reply' => "Chào em. Hiện khoá đang dùng khung demo: xem bài «Tổng quan» và checklist môi trường trong chương 1. Danh mục tài liệu chính thức sẽ cập nhật khi nội dung thật được bổ sung.",
            ],
            [
                'subject' => 'Quiz / assignment tính vào điểm thế nào?',
                'content' => 'Các bài quiz trong khoá và assignment cuối chương có bắt buộc hoàn thành để được tính điểm thành phần không ạ?',
                'reply' => 'Quiz bài học dùng để tự kiểm tra. Điểm chính thức (nếu có) sẽ theo cấu phần lớp tín chỉ / quy định giảng viên — theo dõi thông báo trong khoá và sổ điểm lớp.',
            ],
            [
                'subject' => 'Học online có buổi offline/lab không?',
                'content' => 'Em thấy có mục offline lab trong chương trình. Có lịch cố định tại PTIT không ạ?',
                'reply' => 'Phiên offline trong khung demo mang tính minh họa. Lịch lab thật (nếu mở) sẽ gắn với lớp hành chính / lớp tín chỉ và thông báo trước trên LMS.',
            ],
        ];

        $qaCount = 0;
        $replyCount = 0;

        foreach (array_slice($qaBlueprints, 0, 2) as $i => $qaSeed) {
            $student = $students[($courseIndex + $i * 5 + 1) % $students->count()];

            $qa = CourseQa::query()->updateOrCreate(
                [
                    'course_id' => $course->id,
                    'user_id' => $student->id,
                    'subject' => $qaSeed['subject'],
                ],
                [
                    'lesson_id' => null,
                    'content' => $qaSeed['content'],
                    'created_at' => now()->subDays(6 - $i),
                    'updated_at' => now()->subDays(6 - $i),
                ]
            );
            $qaCount++;

            if ($instructorId) {
                CourseQaReply::query()->updateOrCreate(
                    [
                        'course_qa_id' => $qa->id,
                        'user_id' => $instructorId,
                    ],
                    [
                        'content' => $qaSeed['reply'],
                        'created_at' => now()->subDays(5 - $i),
                        'updated_at' => now()->subDays(5 - $i),
                    ]
                );
                $replyCount++;
            }
        }

        return [$qaCount, $replyCount];
    }

    private function guessLevel(string $title): string
    {
        $t = Str::lower($title);
        if (Str::contains($t, ['nâng cao', 'chuyên sâu', 'advanced', 'capstone', 'đồ án'])) {
            return 'advanced';
        }
        if (Str::contains($t, ['cơ bản', 'nhập môn', 'đại cương', 'cơ sở', 'introduction', 'toán'])) {
            return 'beginner';
        }
        return 'intermediate';
    }

    private function guessTrailer(Course $course): string
    {
        $cat = $course->category?->slug ?: '';
        $root = $course->category?->parent?->slug ?: $cat;

        $map = [
            'dien-tu-vien-thong' => 'https://www.youtube.com/watch?v=a-8MPGzrChQ',
            'mang-vien-thong' => 'https://www.youtube.com/watch?v=a-8MPGzrChQ',
            'dien-tu-iot' => 'https://www.youtube.com/watch?v=0-S5a0eXPoc',
            'cong-nghe-thong-tin' => 'https://www.youtube.com/watch?v=zOjov-2OZ0E',
            'lap-trinh-phan-mem' => 'https://www.youtube.com/watch?v=zOjov-2OZ0E',
            'csdl-he-thong' => 'https://www.youtube.com/watch?v=HXV3zeQKqGY',
            'tri-tue-nhan-tao' => 'https://www.youtube.com/watch?v=aircAruvnKk',
            'quan-tri-kinh-doanh' => 'https://www.youtube.com/watch?v=Vbn3fNPP0Hk',
            'marketing-thuong-mai' => 'https://www.youtube.com/watch?v=bixR-KIJKYM',
            'quan-ly-du-an' => 'https://www.youtube.com/watch?v=gT5BBwi8aTk',
        ];

        return $map[$cat] ?? $map[$root] ?? 'https://www.youtube.com/watch?v=zOjov-2OZ0E';
    }
}
