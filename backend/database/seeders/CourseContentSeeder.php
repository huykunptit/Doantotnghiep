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
use Database\Seeders\Support\SubjectQuizBank;
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
            if ($course->lessons()->exists()) {
                $this->command?->info("Refreshing subject quizzes for #{$course->id}: {$course->title}");
                $this->refreshCourseQuizzes($course);
                continue;
            }
            $this->command?->info("Seeding content for course #{$course->id}: {$course->title}");
            $this->seedCourse($course);
        }
    }

    /** Làm mới ngân hàng câu hỏi + gắn lại quiz theo đúng nội dung môn. */
    private function refreshCourseQuizzes(Course $course): void
    {
        $bank = QuestionBank::query()->updateOrCreate(
            ['course_id' => $course->id, 'name' => 'Ngân hàng câu hỏi chính'],
            ['description' => "Ngân hàng câu hỏi theo đề cương môn «{$course->title}»."],
        );

        [$basicGroup, $advancedGroup] = $this->ensureQuestionGroups($course, $bank);
        $bankQuestions = $this->ensureBankQuestions($course, $bank, $basicGroup, $advancedGroup);

        $quizLessons = Lesson::query()
            ->where('course_id', $course->id)
            ->where('type', 'quiz')
            ->orderBy('order')
            ->get();

        foreach ($quizLessons as $lesson) {
            $this->seedQuiz($course, $lesson, $bank, $bankQuestions, [
                'title' => $lesson->title,
                'asset' => str_contains(mb_strtolower($lesson->title), 'tổng hợp') ? 'quiz-final' : 'quiz-foundation',
            ]);
        }

        $this->seedCourseFinalExam($course, $bank, $bankQuestions);
    }

    private function seedCourse(Course $course): void
    {
        $videoMap = [
                // --- Lập trình & CNTT (web-dev, database, devops, mobile-dev) ---
                'intro-overview' => [
                    'lap-trinh-cntt' => 'https://www.youtube.com/watch?v=zOjov-2OZ0E', // Roadmap Web Dev
                    'web-dev'        => 'https://www.youtube.com/watch?v=zOjov-2OZ0E',
                    'database'       => 'https://www.youtube.com/watch?v=HXV3zeQKqGY', // SQL Tutorial
                    'devops'         => 'https://www.youtube.com/watch?v=a-8MPGzrChQ', // DevOps Overview
                    'mobile-dev'     => 'https://www.youtube.com/watch?v=0-S5a0eXPoc', // Flutter Intro
                    'thiet-ke'       => 'https://www.youtube.com/watch?v=c9Wg6Cb_YlU', // UI/UX Intro
                    'ui-ux'          => 'https://www.youtube.com/watch?v=c9Wg6Cb_YlU',
                    'do-hoa'         => 'https://www.youtube.com/watch?v=3_PnuDQ4pNg', // Graphic Design
                    'kinh-doanh'     => 'https://www.youtube.com/watch?v=Vbn3fNPP0Hk', // Business Intro
                    'marketing'      => 'https://www.youtube.com/watch?v=bixR-KIJKYM', // Digital Marketing
                    'quan-ly-du-an'  => 'https://www.youtube.com/watch?v=gT5BBwi8aTk', // Project Mgmt
                    'ngoai-ngu'      => 'https://www.youtube.com/watch?v=1MVKjd_FVG4', // English Learning
                    'tieng-anh'      => 'https://www.youtube.com/watch?v=1MVKjd_FVG4',
                    'tieng-nhat'     => 'https://www.youtube.com/watch?v=rGrBHiuPlT0', // Japanese Intro
                    '_default'       => 'https://www.youtube.com/watch?v=zOjov-2OZ0E',
                ],
                'setup-environment' => [
                    'lap-trinh-cntt' => 'https://www.youtube.com/watch?v=bMknfKXIFA8', // VS Code Setup
                    'web-dev'        => 'https://www.youtube.com/watch?v=bMknfKXIFA8',
                    'database'       => 'https://www.youtube.com/watch?v=uj4OYk5nKCg', // MySQL Setup
                    'devops'         => 'https://www.youtube.com/watch?v=pg19Z8LL06w', // Docker Setup
                    'mobile-dev'     => 'https://www.youtube.com/watch?v=1ukSR1GRtMU', // Flutter Setup
                    'thiet-ke'       => 'https://www.youtube.com/watch?v=Cx2dkpBxst8', // Figma Setup
                    'ui-ux'          => 'https://www.youtube.com/watch?v=Cx2dkpBxst8',
                    'do-hoa'         => 'https://www.youtube.com/watch?v=ysz5S6PUM-U', // Photoshop Setup
                    'kinh-doanh'     => 'https://www.youtube.com/watch?v=qz0aGYrrlhU', // Excel/Tools Setup
                    'ngoai-ngu'      => 'https://www.youtube.com/watch?v=WNSRFTbAAPk', // Anki Setup
                    '_default'       => 'https://www.youtube.com/watch?v=bMknfKXIFA8',
                ],
                'core-concepts' => [
                    'lap-trinh-cntt' => 'https://www.youtube.com/watch?v=SqcY0GlETPk', // HTML/CSS Basics
                    'web-dev'        => 'https://www.youtube.com/watch?v=SqcY0GlETPk',
                    'database'       => 'https://www.youtube.com/watch?v=p3qvj9hO_Bo', // SQL Core Concepts
                    'devops'         => 'https://www.youtube.com/watch?v=kTp5xUtcalw', // CI/CD Concepts
                    'mobile-dev'     => 'https://www.youtube.com/watch?v=x0uinJvhNxI', // Flutter Widgets
                    'thiet-ke'       => 'https://www.youtube.com/watch?v=II-6dDzc-80', // Design Principles
                    'ui-ux'          => 'https://www.youtube.com/watch?v=II-6dDzc-80',
                    'do-hoa'         => 'https://www.youtube.com/watch?v=x3zJ0DqKJtQ', // Color Theory
                    'kinh-doanh'     => 'https://www.youtube.com/watch?v=mEqu4S5FP_Y', // Business Fundamentals
                    'marketing'      => 'https://www.youtube.com/watch?v=bixR-KIJKYM',
                    'ngoai-ngu'      => 'https://www.youtube.com/watch?v=V_TnqZoHkFM', // English Grammar
                    'tieng-anh'      => 'https://www.youtube.com/watch?v=V_TnqZoHkFM',
                    'tieng-nhat'     => 'https://www.youtube.com/watch?v=6p9Il_j0zjc', // Hiragana
                    '_default'       => 'https://www.youtube.com/watch?v=SqcY0GlETPk',
                ],
                'practice-walkthrough' => [
                    'lap-trinh-cntt' => 'https://www.youtube.com/watch?v=G3e-cpL7ofc', // Build a website
                    'web-dev'        => 'https://www.youtube.com/watch?v=G3e-cpL7ofc',
                    'database'       => 'https://www.youtube.com/watch?v=7S_tz1z_5bA', // SQL Practice
                    'devops'         => 'https://www.youtube.com/watch?v=fqMOX6JJhGo', // Docker Walkthrough
                    'mobile-dev'     => 'https://www.youtube.com/watch?v=tye0zhHMl5A', // Flutter App Build
                    'thiet-ke'       => 'https://www.youtube.com/watch?v=4W4LvJnNegA', // Figma Prototype
                    'ui-ux'          => 'https://www.youtube.com/watch?v=4W4LvJnNegA',
                    'do-hoa'         => 'https://www.youtube.com/watch?v=r9sVwRVCDC8', // Design Practice
                    'kinh-doanh'     => 'https://www.youtube.com/watch?v=XB5OUQO6O_k', // Business Plan Practice
                    'ngoai-ngu'      => 'https://www.youtube.com/watch?v=HMq2BvAoFns', // English Practice
                    'tieng-nhat'     => 'https://www.youtube.com/watch?v=6p9Il_j0zjc',
                    '_default'       => 'https://www.youtube.com/watch?v=G3e-cpL7ofc',
                ],
                'live-workshop' => [
                    'lap-trinh-cntt' => 'https://www.youtube.com/watch?v=PlxWf493en4', // Live coding
                    'web-dev'        => 'https://www.youtube.com/watch?v=PlxWf493en4',
                    'database'       => 'https://www.youtube.com/watch?v=9GIbChqqMT0', // SQL Live
                    'devops'         => 'https://www.youtube.com/watch?v=LFJfC73g2RE', // K8s Live
                    'mobile-dev'     => 'https://www.youtube.com/watch?v=CDhaS04_kkk', // Flutter Live
                    'thiet-ke'       => 'https://www.youtube.com/watch?v=Oi9ciYM3Rk8', // Design Workshop
                    'kinh-doanh'     => 'https://www.youtube.com/watch?v=v80TV8XBOBE', // Business Workshop
                    'ngoai-ngu'      => 'https://www.youtube.com/watch?v=bCBd_M4aIfA', // Speaking Practice
                    '_default'       => 'https://www.youtube.com/watch?v=PlxWf493en4',
                ],
            ];
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

        [$basicGroup, $advancedGroup] = $this->ensureQuestionGroups($course, $bank);
        $bankQuestions = $this->ensureBankQuestions($course, $bank, $basicGroup, $advancedGroup);

        $lessonBlueprints = $this->lessonBlueprints($course);

        foreach ($lessonBlueprints as $index => $blueprint) {
            $order = $index + 1;
            $section = $sections[$blueprint['section']] ?? $sections[1];
            $type = $blueprint['type'];

        $youtubeUrl = null;
        if (in_array($type, ['video', 'live'], true)) {
            $catSlug = $course->category?->slug ?? '';
            $rootSlug = $course->category?->parent?->slug ?? $catSlug;
            
            $assetMap = $videoMap[$blueprint['asset']] ?? null;
            if ($assetMap) {
                $youtubeUrl = $assetMap[$catSlug] ?? $assetMap[$rootSlug] ?? $assetMap['_default'] ?? null;
            }
            
            if (!$youtubeUrl) {
                $titleLower = \Illuminate\Support\Str::lower($blueprint['title']);
                $keywordMap = [
                    ['keys' => ['tổng quan', 'giới thiệu', 'intro', 'overview'],  'url' => 'https://www.youtube.com/watch?v=zOjov-2OZ0E'],
                    ['keys' => ['thiết lập', 'cài đặt', 'setup', 'môi trường'],   'url' => 'https://www.youtube.com/watch?v=bMknfKXIFA8'],
                    ['keys' => ['kiến thức', 'nền tảng', 'core', 'concepts'],     'url' => 'https://www.youtube.com/watch?v=SqcY0GlETPk'],
                    ['keys' => ['thực hành', 'walkthrough', 'ví dụ mẫu'],          'url' => 'https://www.youtube.com/watch?v=G3e-cpL7ofc'],
                    ['keys' => ['live', 'workshop', 'chia sẻ'],                    'url' => 'https://www.youtube.com/watch?v=PlxWf493en4'],
                ];
                foreach ($keywordMap as $entry) {
                    foreach ($entry['keys'] as $key) {
                        if (\Illuminate\Support\Str::contains($titleLower, $key)) {
                            $youtubeUrl = $entry['url'];
                            break 2;
                        }
                    }
                }
                if (!$youtubeUrl) {
                    $youtubeUrl = 'https://www.youtube.com/watch?v=zOjov-2OZ0E';
                }
            }
        }


            $payload = [
                'title' => $blueprint['title'],
                'description' => $blueprint['description'],
                'duration' => $blueprint['duration'],
                'order' => $order,
                'is_preview' => (bool) ($blueprint['is_preview'] ?? false),
                'video_url' => $youtubeUrl,
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
                        'start_at' => now()->setTime(19, 30),
                        'duration' => 90,
                    ],
                );
                $workshop = OfflineSession::query()->updateOrCreate(
                    ['lesson_id' => $lesson->id, 'class_section_id' => null],
                    [
                        'course_id' => $course->id,
                        'title' => 'QR điểm danh live workshop - '.$course->title,
                        'location' => 'Google Meet / Workshop trực tuyến',
                        'room' => 'ONLINE',
                        'start_at' => now()->setTime(19, 30),
                        'duration' => 90,
                        'max_participants' => 80,
                        'latitude' => null,
                        'longitude' => null,
                        'check_in_radius_meters' => OfflineSession::DEFAULT_CHECK_IN_RADIUS_METERS,
                        'is_active' => true,
                        'qr_enabled' => true,
                        'qr_mode' => OfflineSession::QR_MODE_MANUAL,
                    ],
                );
                $workshop->generateQrToken(14 * 24 * 60);
            }

            if ($type === 'offline') {
                $lab = OfflineSession::query()->updateOrCreate(
                    ['lesson_id' => $lesson->id, 'class_section_id' => null],
                    [
                        'course_id' => $course->id,
                        'location' => $blueprint['location'] ?? 'Phòng Lab A3 - PTIT',
                        'title' => 'QR điểm danh buổi offline - '.$course->title,
                        'room' => 'LAB-A3',
                        'start_at' => now()->subMinutes(15),
                        'duration' => 120,
                        'max_participants' => 35,
                        'latitude' => null,
                        'longitude' => null,
                        'check_in_radius_meters' => OfflineSession::DEFAULT_CHECK_IN_RADIUS_METERS,
                        'is_active' => true,
                        'qr_enabled' => true,
                        'qr_mode' => OfflineSession::QR_MODE_MANUAL,
                    ],
                );
                $lab->generateQrToken(14 * 24 * 60);
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

    /** @return array{0: QuestionGroup, 1: QuestionGroup} */
    private function ensureQuestionGroups(Course $course, QuestionBank $bank): array
    {
        $basic = QuestionGroup::query()->updateOrCreate(
            ['course_id' => $course->id, 'question_bank_id' => $bank->id, 'name' => 'Nhóm kiến thức nền'],
            ['description' => 'Câu hỏi nhận biết / thông hiểu theo đề cương học phần.', 'sort_order' => 1],
        );
        $advanced = QuestionGroup::query()->updateOrCreate(
            ['course_id' => $course->id, 'question_bank_id' => $bank->id, 'name' => 'Vận dụng và tình huống'],
            ['description' => 'Câu hỏi vận dụng, đúng/sai và tình huống nâng cao.', 'sort_order' => 2],
        );

        return [$basic, $advanced];
    }

    private function ensureBankQuestions(
        Course $course,
        QuestionBank $bank,
        QuestionGroup $basicGroup,
        QuestionGroup $advancedGroup,
    ): array {
        $blueprints = SubjectQuizBank::forCourse($course);

        $oldIds = Question::query()->where('question_bank_id', $bank->id)->pluck('id');
        if ($oldIds->isNotEmpty()) {
            DB::table('quiz_question')->whereIn('question_id', $oldIds)->delete();
            DB::table('answers')->whereIn('question_id', $oldIds)->delete();
            Question::query()->whereIn('id', $oldIds)->delete();
        }

        $basic = [];
        $advanced = [];

        foreach ($blueprints as $seed) {
            $difficulty = (int) ($seed['difficulty'] ?? 1);
            $group = $difficulty <= 2 ? $basicGroup : $advancedGroup;
            $question = Question::query()->create([
                'course_id' => $course->id,
                'question_bank_id' => $bank->id,
                'question_group_id' => $group->id,
                'content' => $seed['content'],
                'type' => $seed['type'] ?? 'single_choice',
                'difficulty' => $difficulty,
                'default_score' => $difficulty >= 3 ? 1.5 : 1,
                'explanation' => $seed['explanation'],
            ]);

            foreach ($seed['options'] as $answerIndex => $answer) {
                DB::table('answers')->insert([
                    'question_id' => $question->id,
                    'content' => $answer,
                    'is_correct' => $answerIndex === $seed['correct'],
                    'sort_order' => $answerIndex + 1,
                    'order' => $answerIndex + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $fresh = $question->fresh();
            if ($difficulty <= 2) {
                $basic[] = $fresh;
            } else {
                $advanced[] = $fresh;
            }
        }

        return array_values(array_merge($basic, $advanced));
    }

    private function seedQuiz(Course $course, Lesson $lesson, QuestionBank $bank, array $bankQuestions, array $blueprint): void
    {
        $isFinal = str_contains(Str::lower($blueprint['title'] ?? ''), 'tổng hợp')
            || str_contains((string) ($blueprint['asset'] ?? ''), 'final');
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

        DB::table('quiz_question')->where('quiz_id', $quiz->id)->delete();

        $picks = $isFinal
            ? array_slice($bankQuestions, 0, min(16, count($bankQuestions)))
            : array_slice($bankQuestions, 0, min(8, count($bankQuestions)));

        foreach ($picks as $index => $question) {
            DB::table('quiz_question')->insert([
                'quiz_id' => $quiz->id,
                'question_id' => $question->id,
                'order' => $index + 1,
                'points' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedCourseFinalExam(Course $course, QuestionBank $bank, array $bankQuestions): void
    {
        $quiz = Quiz::query()->updateOrCreate(
            ['course_id' => $course->id, 'scope' => 'course', 'lesson_id' => null, 'exam_id' => null],
            [
                'title' => "Kiểm tra tổng hợp: {$course->title}",
                'description' => "Bài kiểm tra tổng hợp kiến thức chuyên môn của học phần «{$course->title}».",
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

        DB::table('quiz_question')->where('quiz_id', $quiz->id)->delete();

        foreach (array_slice($bankQuestions, 0, min(18, count($bankQuestions))) as $index => $question) {
            DB::table('quiz_question')->insert([
                'quiz_id' => $quiz->id,
                'question_id' => $question->id,
                'order' => $index + 1,
                'points' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
