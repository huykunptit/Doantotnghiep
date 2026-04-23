<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonAttachment;
use App\Models\LessonAssignment;
use App\Models\OfflineSession;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionGroup;
use App\Models\Quiz;
use App\Models\ScormPackage;
use App\Models\Section;
use App\Models\VirtualClass;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CourseContentSeeder extends Seeder
{
    public function run(): void
    {
        $courses = Course::query()->orderBy('id')->get();

        if ($courses->isEmpty()) {
            $this->command?->warn('No courses found. Run the main DatabaseSeeder first.');
            return;
        }

        foreach ($courses as $course) {
            $this->command?->info("Seeding content for course #{$course->id}: {$course->title}");
            $this->seedCourse($course);
        }
    }

    private function seedCourse(Course $course): void
    {
        $sectionBlueprints = [
            ['title' => 'Khởi động và định hướng', 'description' => 'Nhìn toàn cảnh khóa học, thiết lập công cụ và đặt mục tiêu học tập rõ ràng.'],
            ['title' => 'Nội dung trọng tâm', 'description' => 'Đi sâu vào các kiến thức cốt lõi qua video, bài thực hành và quiz kiểm tra.'],
            ['title' => 'Thực chiến và đánh giá', 'description' => 'Áp dụng qua assignment, buổi live và workshop, kết thúc bằng bài kiểm tra tổng hợp.'],
        ];

        $sections = [];
        foreach ($sectionBlueprints as $index => $blueprint) {
            $position = $index + 1;
            $sections[$position] = Section::query()->updateOrCreate(
                ['course_id' => $course->id, 'position' => $position],
                ['title' => $blueprint['title'], 'description' => $blueprint['description']],
            );
        }

        $bank = QuestionBank::query()->updateOrCreate(
            ['course_id' => $course->id, 'name' => 'Ngân hàng câu hỏi chính'],
            ['description' => 'Ngân hàng câu hỏi chuẩn hóa cho toàn khóa, dùng chung cho quiz và kiểm tra.'],
        );

        $group = QuestionGroup::query()->updateOrCreate(
            ['course_id' => $course->id, 'question_bank_id' => $bank->id, 'name' => 'Nhóm kiến thức nền'],
            ['description' => 'Các câu hỏi cơ bản, áp dụng cho phần lớn bài quiz trong khóa.', 'sort_order' => 1],
        );

        $bankQuestions = $this->ensureBankQuestions($course, $bank, $group);

        $lessonBlueprints = $this->lessonBlueprints($course);

        foreach ($lessonBlueprints as $index => $blueprint) {
            $order = $index + 1;
            $section = $sections[$blueprint['section']] ?? $sections[1];
            $type = $blueprint['type'];

            $payload = [
                'title' => $blueprint['title'],
                'description' => $blueprint['description'],
                'duration' => $blueprint['duration'],
                'order' => $order,
                'is_preview' => (bool) ($blueprint['is_preview'] ?? false),
                'video_url' => in_array($type, ['video', 'live'], true) ? "demo/{$course->slug}/{$blueprint['asset']}.mp4" : null,
                'section_id' => $section->id,
                'type' => $type,
                'video_size' => in_array($type, ['video', 'live'], true) ? (180 + $order * 14) . ' MB' : null,
                'video_status' => in_array($type, ['video', 'live'], true) ? 'ready' : 'pending',
            ];

            $lesson = Lesson::query()->updateOrCreate(
                ['course_id' => $course->id, 'order' => $order],
                $payload,
            );

            $this->attachSupportingMaterials($course, $lesson, $blueprint);

            if ($type === 'quiz') {
                $this->seedQuiz($course, $lesson, $bank, $bankQuestions, $blueprint);
            }

            if ($type === 'assignment') {
                LessonAssignment::query()->updateOrCreate(
                    ['lesson_id' => $lesson->id],
                    [
                        'instructions' => $blueprint['instructions']
                            ?? 'Hoàn thành bài tập theo yêu cầu và nộp file kết quả trước hạn.',
                        'max_file_size' => 10240,
                        'allowed_extensions' => 'pdf,doc,docx,zip,pptx,png,jpg',
                        'due_at' => now()->addDays(10),
                    ],
                );
            }

            if ($type === 'live') {
                VirtualClass::query()->updateOrCreate(
                    ['lesson_id' => $lesson->id],
                    [
                        'provider' => 'google_meet',
                        'meeting_id' => strtoupper(Str::random(10)),
                        'meeting_password' => Str::upper(Str::random(6)),
                        'join_url' => 'https://meet.google.com/' . Str::lower(Str::random(3)) . '-' . Str::lower(Str::random(4)) . '-' . Str::lower(Str::random(3)),
                        'start_url' => 'https://meet.google.com/start/' . Str::lower(Str::random(12)),
                        'start_at' => now()->addDays(3)->setTime(19, 30),
                        'duration' => 90,
                    ],
                );
            }

            if ($type === 'offline') {
                OfflineSession::query()->updateOrCreate(
                    ['lesson_id' => $lesson->id],
                    [
                        'location' => $blueprint['location'] ?? 'Phòng Lab A3 - PTIT',
                        'start_at' => now()->addDays(7)->setTime(8, 30),
                        'duration' => 120,
                        'max_participants' => 35,
                    ],
                );
            }

            if (!empty($blueprint['scorm'])) {
                ScormPackage::query()->updateOrCreate(
                    ['lesson_id' => $lesson->id],
                    [
                        'uuid' => (string) Str::uuid(),
                        'version' => '1.2',
                        'entry_url' => "scorm/{$course->slug}/{$blueprint['asset']}/index.html",
                        'identifier' => Str::slug($course->slug . '-' . $blueprint['asset'] . '-scorm'),
                        'title' => 'SCORM - ' . $blueprint['title'],
                    ],
                );
            }
        }

        $this->seedCourseFinalExam($course, $bank, $bankQuestions);
    }

    private function attachSupportingMaterials(Course $course, Lesson $lesson, array $blueprint): void
    {
        $asset = $blueprint['asset'];

        LessonAttachment::query()->updateOrCreate(
            ['lesson_id' => $lesson->id, 'original_name' => "{$asset}-slides.pdf"],
            [
                'file_path' => "attachments/{$course->slug}/{$asset}-slides.pdf",
                'file_size' => '1.8 MB',
                'mime_type' => 'application/pdf',
            ],
        );

        LessonAttachment::query()->updateOrCreate(
            ['lesson_id' => $lesson->id, 'original_name' => "{$asset}-worksheet.docx"],
            [
                'file_path' => "attachments/{$course->slug}/{$asset}-worksheet.docx",
                'file_size' => '0.9 MB',
                'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ],
        );
    }

    private function lessonBlueprints(Course $course): array
    {
        $topic = $course->title;

        return [
            [
                'section' => 1,
                'title' => "Tổng quan khóa {$topic}",
                'description' => "Giới thiệu mục tiêu, lộ trình và cách khai thác tốt nhất khóa {$topic}.",
                'type' => 'video',
                'duration' => 900,
                'is_preview' => true,
                'asset' => 'intro-overview',
                'scorm' => true,
            ],
            [
                'section' => 1,
                'title' => "Thiết lập công cụ và môi trường cho {$topic}",
                'description' => 'Chuẩn bị phần mềm, tài liệu và các bước cài đặt để sẵn sàng học tập.',
                'type' => 'video',
                'duration' => 1080,
                'asset' => 'setup-environment',
            ],
            [
                'section' => 2,
                'title' => "Kiến thức nền tảng của {$topic}",
                'description' => 'Phân tích các khái niệm cốt lõi và ví dụ trực quan cho người mới.',
                'type' => 'video',
                'duration' => 1260,
                'asset' => 'core-concepts',
                'scorm' => true,
            ],
            [
                'section' => 2,
                'title' => 'Quiz kiểm tra phần nền tảng',
                'description' => 'Đánh giá nhanh mức nắm lý thuyết trước khi đi vào thực hành.',
                'type' => 'quiz',
                'duration' => 900,
                'asset' => 'quiz-foundation',
            ],
            [
                'section' => 2,
                'title' => 'Thực hành theo ví dụ mẫu',
                'description' => 'Làm theo từng bước để hoàn thành một ví dụ điển hình của khóa học.',
                'type' => 'video',
                'duration' => 1380,
                'asset' => 'practice-walkthrough',
            ],
            [
                'section' => 3,
                'title' => 'Assignment tổng hợp kỹ năng',
                'description' => 'Bài tập mở cho phép học viên vận dụng sáng tạo những gì đã học.',
                'type' => 'assignment',
                'duration' => 1500,
                'asset' => 'final-assignment',
                'instructions' => "Hoàn thành bài tập tổng hợp kỹ năng của khóa {$topic}, nộp file và mô tả ngắn gọn cách giải quyết.",
            ],
            [
                'section' => 3,
                'title' => 'Live workshop chia sẻ kinh nghiệm',
                'description' => 'Buổi live mentor chia sẻ tình huống thực tế, Q&A trực tiếp với học viên.',
                'type' => 'live',
                'duration' => 5400,
                'asset' => 'live-workshop',
            ],
            [
                'section' => 3,
                'title' => 'Offline lab thực hành theo nhóm',
                'description' => 'Buổi offline theo nhóm, thực hành trực tiếp và trao đổi dưới sự hướng dẫn của giảng viên.',
                'type' => 'offline',
                'duration' => 7200,
                'asset' => 'offline-lab',
                'location' => 'Phòng Lab PTIT - Tầng 5',
            ],
            [
                'section' => 3,
                'title' => 'Quiz tổng hợp cuối khóa',
                'description' => 'Bài kiểm tra cuối khóa tổng hợp toàn bộ kiến thức và kỹ năng.',
                'type' => 'quiz',
                'duration' => 1800,
                'asset' => 'quiz-final',
            ],
        ];
    }

    private function ensureBankQuestions(Course $course, QuestionBank $bank, QuestionGroup $group): array
    {
        $blueprints = [
            [
                'content' => "Mục tiêu chính của khóa {$course->title} là gì?",
                'options' => [
                    'Hiểu và áp dụng được kiến thức vào tình huống thực tế',
                    'Chỉ cần ghi nhớ tên các bài học',
                    'Không cần theo dõi tiến độ học tập',
                    'Bỏ qua phần thực hành',
                ],
                'correct' => 0,
                'difficulty' => 1,
                'explanation' => 'Mục tiêu cuối cùng luôn là áp dụng được vào công việc hoặc dự án thực tế.',
            ],
            [
                'content' => 'Yếu tố nào quan trọng nhất khi lập kế hoạch học một khóa mới?',
                'options' => [
                    'Mục tiêu rõ ràng và lịch học ổn định',
                    'Chỉ cần học khi có thời gian',
                    'Bỏ qua phần mục tiêu, học theo ngẫu hứng',
                    'Không cần chuẩn bị tài nguyên',
                ],
                'correct' => 0,
                'difficulty' => 1,
                'explanation' => 'Mục tiêu và lịch học rõ ràng là nền tảng để học đều và bền.',
            ],
            [
                'content' => 'Khi học viên gặp khó khăn với một bài học, việc hợp lý nhất là gì?',
                'options' => [
                    'Đặt câu hỏi trong phần hỏi đáp và xem lại tài liệu',
                    'Bỏ hẳn bài học đó',
                    'Chuyển sang khóa khác',
                    'Đợi đến khi khóa kết thúc mới hỏi',
                ],
                'correct' => 0,
                'difficulty' => 2,
                'explanation' => 'Trao đổi sớm giúp tránh hiểu sai và rút ngắn thời gian giải quyết.',
            ],
            [
                'content' => 'Quiz trong khóa học có vai trò gì đối với học viên?',
                'options' => [
                    'Củng cố kiến thức và xác định lỗ hổng để cải thiện',
                    'Tăng độ khó một cách không cần thiết',
                    'Chỉ để làm đẹp báo cáo',
                    'Không có tác dụng đáng kể',
                ],
                'correct' => 0,
                'difficulty' => 2,
                'explanation' => 'Quiz giúp người học phản hồi nhanh về mức độ hiểu bài của bản thân.',
            ],
            [
                'content' => 'Vì sao nên hoàn thành assignment thay vì chỉ xem video thụ động?',
                'options' => [
                    'Assignment buộc học viên áp dụng kiến thức và nhận phản hồi có hệ thống',
                    'Video luôn đầy đủ và không cần thực hành',
                    'Chỉ cần nghe giảng là nhớ lâu',
                    'Assignment không quan trọng',
                ],
                'correct' => 0,
                'difficulty' => 2,
                'explanation' => 'Thực hành + phản hồi là công thức ghi nhớ bền vững nhất trong học tập.',
            ],
            [
                'content' => 'Khi có buổi live, học viên nên chuẩn bị gì?',
                'options' => [
                    'Xem lại bài liên quan và chuẩn bị câu hỏi',
                    'Vào lớp mà không cần chuẩn bị',
                    'Chỉ cần ghi âm lại để xem sau',
                    'Chờ giảng viên hỏi mới trả lời',
                ],
                'correct' => 0,
                'difficulty' => 2,
                'explanation' => 'Chuẩn bị trước giúp buổi live tập trung vào thảo luận giá trị, không lặp lại kiến thức cơ bản.',
            ],
            [
                'content' => 'Làm thế nào để theo dõi tiến độ học tập hiệu quả?',
                'options' => [
                    'Dùng tính năng theo dõi tiến độ của hệ thống và đối chiếu với mục tiêu',
                    'Chỉ nhớ trong đầu',
                    'Viết tay trên giấy rồi bỏ quên',
                    'Không cần theo dõi',
                ],
                'correct' => 0,
                'difficulty' => 3,
                'explanation' => 'Hệ thống cung cấp dashboard tiến độ để học viên nhìn thấy mình đang ở đâu.',
            ],
            [
                'content' => 'Vai trò của question bank trong hệ thống quiz là gì?',
                'options' => [
                    'Chuẩn hóa câu hỏi, tái sử dụng và tạo đề ngẫu nhiên',
                    'Chỉ để chứa câu hỏi duy nhất cho một quiz',
                    'Làm rối hệ thống',
                    'Không liên quan đến quiz',
                ],
                'correct' => 0,
                'difficulty' => 3,
                'explanation' => 'Question bank giúp tái sử dụng và mở rộng bài thi theo thời gian.',
            ],
            [
                'content' => 'Khi đánh giá một câu hỏi chất lượng, cần chú ý điều gì?',
                'options' => [
                    'Đáp án duy nhất đúng, phương án nhiễu hợp lý và có giải thích',
                    'Đáp án nào cũng được',
                    'Không cần giải thích',
                    'Câu hỏi nhiều đáp án đúng để gây khó',
                ],
                'correct' => 0,
                'difficulty' => 3,
                'explanation' => 'Câu hỏi tốt có cấu trúc rõ ràng và giải thích đáp án để học viên học thêm.',
            ],
            [
                'content' => 'Sau khi hoàn thành khóa học, hành động nào giúp kiến thức được giữ lâu nhất?',
                'options' => [
                    'Áp dụng vào dự án thực tế hoặc chia sẻ lại cho người khác',
                    'Xếp tài liệu vào tủ và không xem lại',
                    'Chỉ lưu chứng chỉ',
                    'Bỏ qua bước ôn tập',
                ],
                'correct' => 0,
                'difficulty' => 3,
                'explanation' => 'Dạy lại hoặc ứng dụng vào dự án là cách học sâu hiệu quả nhất.',
            ],
        ];

        $questions = [];

        foreach ($blueprints as $index => $seed) {
            $question = Question::query()->updateOrCreate(
                ['course_id' => $course->id, 'content' => $seed['content']],
                [
                    'question_bank_id' => $bank->id,
                    'question_group_id' => $group->id,
                    'type' => 'single_choice',
                    'difficulty' => $seed['difficulty'],
                    'explanation' => $seed['explanation'],
                ],
            );

            foreach ($seed['options'] as $answerIndex => $answer) {
                DB::table('answers')->updateOrInsert(
                    ['question_id' => $question->id, 'content' => $answer],
                    [
                        'question_id' => $question->id,
                        'content' => $answer,
                        'is_correct' => $answerIndex === $seed['correct'],
                        'sort_order' => $answerIndex + 1,
                        'order' => $answerIndex + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }

            $questions[] = $question->fresh();
        }

        return $questions;
    }

    private function seedQuiz(Course $course, Lesson $lesson, QuestionBank $bank, array $bankQuestions, array $blueprint): void
    {
        $isFinal = str_contains(Str::lower($blueprint['title']), 'tổng hợp') || str_contains($blueprint['asset'], 'final');
        $passScore = $isFinal ? 80 : 60;

        $quiz = Quiz::query()->updateOrCreate(
            ['lesson_id' => $lesson->id],
            [
                'course_id' => $course->id,
                'scope' => 'lesson',
                'title' => $lesson->title,
                'description' => $lesson->description,
                'time_limit' => $isFinal ? 30 : 15,
                'pass_score' => $passScore,
                'settings' => [
                    'randomize' => true,
                    'show_result' => true,
                    'source' => 'question_bank',
                    'question_bank_id' => $bank->id,
                ],
            ],
        );

        $picks = $isFinal
            ? $bankQuestions
            : array_slice($bankQuestions, 0, min(5, count($bankQuestions)));

        foreach ($picks as $index => $question) {
            DB::table('quiz_question')->updateOrInsert(
                ['quiz_id' => $quiz->id, 'question_id' => $question->id],
                [
                    'quiz_id' => $quiz->id,
                    'question_id' => $question->id,
                    'order' => $index + 1,
                    'points' => 10,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    private function seedCourseFinalExam(Course $course, QuestionBank $bank, array $bankQuestions): void
    {
        $quiz = Quiz::query()->updateOrCreate(
            ['course_id' => $course->id, 'scope' => 'course', 'lesson_id' => null, 'exam_id' => null],
            [
                'title' => "Kiểm tra tổng hợp: {$course->title}",
                'description' => 'Bài kiểm tra ở cấp khóa học, tổng hợp toàn bộ kiến thức từ ngân hàng câu hỏi.',
                'time_limit' => 45,
                'pass_score' => 80,
                'settings' => [
                    'randomize' => true,
                    'show_result' => false,
                    'source' => 'question_bank',
                    'question_bank_id' => $bank->id,
                ],
            ],
        );

        foreach ($bankQuestions as $index => $question) {
            DB::table('quiz_question')->updateOrInsert(
                ['quiz_id' => $quiz->id, 'question_id' => $question->id],
                [
                    'quiz_id' => $quiz->id,
                    'question_id' => $question->id,
                    'order' => $index + 1,
                    'points' => 10,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }
}
