<?php

namespace Database\Seeders;

use App\Models\AdministrativeClassTerm;
use App\Models\AssignmentSubmission;
use App\Models\CareerPath;
use App\Models\Enrollment;
use App\Models\GradeComponent;
use App\Models\GradeEntry;
use App\Models\Lesson;
use App\Models\LessonAssignment;
use App\Models\LessonProgress;
use App\Models\OfflineSession;
use App\Models\OfflineSessionAttendance;
use App\Models\PointTransaction;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Term;
use App\Models\User;
use App\Models\UserCareerPath;
use App\Services\PointService;
use Database\Seeders\Support\SeededQuizAttempt;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seed du lieu hoc TAP RIENG cho tung hoc vien demo.
 *
 * Muc tieu:
 *  - Dashboard SV (/me/dashboard, transcript, learning-path)
 *  - Danh gia ket qua CTĐT (/me/curriculum-evaluation)
 *  - Analytics L&D admin (progress, at-risk, completion)
 *  - Diem thuong / streak / career path
 *
 * Moi studentN@lms.com co persona khac nhau (GPA, mon manh/yeu, muc do tham gia).
 *
 * Chay:
 *   php artisan db:seed --class=StudentLearningSeeder
 *
 * Nen chay SAU AcademicSeeder + AcademicExtrasSeeder + MarketplaceSeeder + CareerPathSeeder.
 */
class StudentLearningSeeder extends Seeder
{
    /**
     * Persona theo email (student1..student18).
     *
     * @var array<string, array{
     *   label: string,
     *   level: string,
     *   base: float,
     *   spread: float,
     *   strengths: list<string>,
     *   weaknesses: list<string>,
     *   engagement: float,
     *   attendance: float,
     *   assignment: float,
     *   streak: int,
     *   points: int,
     *   path?: string|null
     * }>
     */
    private const PERSONAS = [
        'student1@lms.com' => [
            'label' => 'Xuat sac - Lap trinh / AI',
            'level' => 'excellent',
            'base' => 8.8,
            'spread' => 0.6,
            'strengths' => ['lap trinh', 'python', 'c++', 'ai', 'thuật toán', 'thuat toan', 'cấu trúc dữ liệu', 'cau truc du lieu', 'web'],
            'weaknesses' => ['triết', 'triet', 'chính trị', 'chinh tri', 'pháp luật', 'phap luat'],
            'engagement' => 0.95,
            'attendance' => 0.96,
            'assignment' => 1.0,
            'streak' => 21,
            'points' => 1850,
            'path' => 'fullstack-python-a-z',
        ],
        'student2@lms.com' => [
            'label' => 'Gioi - Mang / Bao mat',
            'level' => 'good',
            'base' => 8.1,
            'spread' => 0.7,
            'strengths' => ['mạng', 'mang', 'bảo mật', 'bao mat', 'truyền thông', 'truyen thong', 'hệ điều hành', 'he dieu hanh', 'linux'],
            'weaknesses' => ['đồ họa', 'do hoa', 'ui', 'ux', 'thiết kế', 'thiet ke'],
            'engagement' => 0.82,
            'attendance' => 0.88,
            'assignment' => 0.85,
            'streak' => 12,
            'points' => 1120,
            'path' => 'devops-cloud-path',
        ],
        'student3@lms.com' => [
            'label' => 'Gioi - Frontend / UX',
            'level' => 'good',
            'base' => 8.0,
            'spread' => 0.8,
            'strengths' => ['web', 'giao diện', 'giao dien', 'ui', 'ux', 'thiết kế', 'thiet ke', 'javascript', 'vue', 'html'],
            'weaknesses' => ['vật lý', 'vat ly', 'mạng', 'mang', 'phần cứng', 'phan cung'],
            'engagement' => 0.88,
            'attendance' => 0.90,
            'assignment' => 0.95,
            'streak' => 14,
            'points' => 1280,
            'path' => 'frontend-vue-nuxt',
        ],
        'student4@lms.com' => [
            'label' => 'Trung binh - Yeu toan',
            'level' => 'average',
            'base' => 6.6,
            'spread' => 1.0,
            'strengths' => ['tin học', 'tin hoc', 'ứng dụng', 'ung dung', 'office', 'kỹ năng mềm', 'ky nang mem'],
            'weaknesses' => ['giải tích', 'giai tich', 'đại số', 'dai so', 'toán', 'toan', 'xác suất', 'xac suat', 'rời rạc', 'roi rac'],
            'engagement' => 0.55,
            'attendance' => 0.70,
            'assignment' => 0.60,
            'streak' => 3,
            'points' => 420,
            'path' => null,
        ],
        'student5@lms.com' => [
            'label' => 'Xuat sac - Tong hop',
            'level' => 'excellent',
            'base' => 9.0,
            'spread' => 0.5,
            'strengths' => ['lập trình', 'lap trinh', 'cơ sở dữ liệu', 'co so du lieu', 'database', 'sql', 'thuật toán', 'thuat toan', 'oop'],
            'weaknesses' => ['thể chất', 'the chat', 'quân sự', 'quan su'],
            'engagement' => 0.98,
            'attendance' => 0.98,
            'assignment' => 1.0,
            'streak' => 30,
            'points' => 2400,
            'path' => 'backend-laravel-php',
        ],
        'student6@lms.com' => [
            'label' => 'Kha - Soft skills / BA',
            'level' => 'good',
            'base' => 7.6,
            'spread' => 0.8,
            'strengths' => ['kinh tế', 'kinh te', 'quản trị', 'quan tri', 'marketing', 'giao tiếp', 'giao tiep', 'dự án', 'du an', 'ba'],
            'weaknesses' => ['lập trình', 'lap trinh', 'c++', 'mạng', 'mang', 'hệ điều hành', 'he dieu hanh'],
            'engagement' => 0.75,
            'attendance' => 0.85,
            'assignment' => 0.80,
            'streak' => 9,
            'points' => 860,
            'path' => null,
        ],
        'student7@lms.com' => [
            'label' => 'At-risk - It tham gia',
            'level' => 'struggling',
            'base' => 5.2,
            'spread' => 1.2,
            'strengths' => ['thể chất', 'the chat'],
            'weaknesses' => ['lập trình', 'lap trinh', 'toán', 'toan', 'cơ sở dữ liệu', 'co so du lieu', 'web', 'mạng', 'mang'],
            'engagement' => 0.22,
            'attendance' => 0.45,
            'assignment' => 0.30,
            'streak' => 0,
            'points' => 80,
            'path' => null,
        ],
        'student8@lms.com' => [
            'label' => 'Gioi - Backend / Database',
            'level' => 'good',
            'base' => 8.2,
            'spread' => 0.7,
            'strengths' => ['cơ sở dữ liệu', 'co so du lieu', 'sql', 'database', 'php', 'laravel', 'backend', 'api', 'web'],
            'weaknesses' => ['đồ họa', 'do hoa', 'vật lý', 'vat ly', 'điện tử', 'dien tu'],
            'engagement' => 0.86,
            'attendance' => 0.92,
            'assignment' => 0.90,
            'streak' => 16,
            'points' => 1450,
            'path' => 'backend-laravel-php',
        ],
        'student9@lms.com' => [
            'label' => 'Trung binh - Deu',
            'level' => 'average',
            'base' => 7.0,
            'spread' => 0.6,
            'strengths' => ['tin học', 'tin hoc', 'nhập môn', 'nhap mon'],
            'weaknesses' => ['nâng cao', 'nang cao', 'bảo mật', 'bao mat'],
            'engagement' => 0.60,
            'attendance' => 0.78,
            'assignment' => 0.65,
            'streak' => 5,
            'points' => 510,
            'path' => 'mobile-flutter-path',
        ],
        'student10@lms.com' => [
            'label' => 'Kha - Ngoai ngu / Ly luan',
            'level' => 'good',
            'base' => 7.8,
            'spread' => 0.7,
            'strengths' => ['tiếng anh', 'tieng anh', 'english', 'triết', 'triet', 'chính trị', 'chinh tri', 'pháp luật', 'phap luat', 'xã hội', 'xa hoi'],
            'weaknesses' => ['lập trình', 'lap trinh', 'mạng', 'mang', 'c++', 'python'],
            'engagement' => 0.70,
            'attendance' => 0.90,
            'assignment' => 0.85,
            'streak' => 8,
            'points' => 740,
            'path' => null,
        ],
        'student11@lms.com' => [
            'label' => 'Gioi - DevOps / He thong',
            'level' => 'good',
            'base' => 8.0,
            'spread' => 0.8,
            'strengths' => ['devops', 'docker', 'linux', 'hệ điều hành', 'he dieu hanh', 'mạng', 'mang', 'cloud', 'triển khai', 'trien khai'],
            'weaknesses' => ['marketing', 'kế toán', 'ke toan', 'đồ họa', 'do hoa'],
            'engagement' => 0.84,
            'attendance' => 0.88,
            'assignment' => 0.88,
            'streak' => 11,
            'points' => 1190,
            'path' => 'devops-cloud-path',
        ],
        'student12@lms.com' => [
            'label' => 'Kha - Mobile',
            'level' => 'average',
            'base' => 7.2,
            'spread' => 0.9,
            'strengths' => ['mobile', 'flutter', 'android', 'ứng dụng', 'ung dung', 'ui'],
            'weaknesses' => ['mạng', 'mang', 'vật lý', 'vat ly', 'đại số', 'dai so'],
            'engagement' => 0.68,
            'attendance' => 0.75,
            'assignment' => 0.70,
            'streak' => 6,
            'points' => 630,
            'path' => 'mobile-flutter-path',
        ],
        'student13@lms.com' => [
            'label' => 'Xuat sac - Toan / Thuat toan',
            'level' => 'excellent',
            'base' => 8.9,
            'spread' => 0.5,
            'strengths' => ['toán', 'toan', 'giải tích', 'giai tich', 'đại số', 'dai so', 'rời rạc', 'roi rac', 'thuật toán', 'thuat toan', 'xác suất', 'xac suat'],
            'weaknesses' => ['thể chất', 'the chat', 'marketing'],
            'engagement' => 0.92,
            'attendance' => 0.94,
            'assignment' => 0.95,
            'streak' => 18,
            'points' => 1680,
            'path' => 'fullstack-python-a-z',
        ],
        'student14@lms.com' => [
            'label' => 'At-risk nhe - Bo buoi',
            'level' => 'struggling',
            'base' => 5.8,
            'spread' => 1.1,
            'strengths' => ['nhập môn', 'nhap mon', 'tin học', 'tin hoc'],
            'weaknesses' => ['cấu trúc', 'cau truc', 'oop', 'cơ sở dữ liệu', 'co so du lieu', 'mạng', 'mang'],
            'engagement' => 0.30,
            'attendance' => 0.50,
            'assignment' => 0.35,
            'streak' => 1,
            'points' => 150,
            'path' => null,
        ],
        'student15@lms.com' => [
            'label' => 'Kha - Dien tu / Vat ly',
            'level' => 'good',
            'base' => 7.7,
            'spread' => 0.8,
            'strengths' => ['điện tử', 'dien tu', 'vật lý', 'vat ly', 'mạch', 'mach', 'viễn thông', 'vien thong', 'tín hiệu', 'tin hieu'],
            'weaknesses' => ['web', 'marketing', 'quản trị', 'quan tri'],
            'engagement' => 0.72,
            'attendance' => 0.82,
            'assignment' => 0.75,
            'streak' => 7,
            'points' => 690,
            'path' => null,
        ],
        'student16@lms.com' => [
            'label' => 'Gioi - Fullstack',
            'level' => 'good',
            'base' => 8.3,
            'spread' => 0.6,
            'strengths' => ['web', 'lập trình', 'lap trinh', 'javascript', 'php', 'vue', 'sql', 'api', 'fullstack'],
            'weaknesses' => ['triết', 'triet', 'thể chất', 'the chat'],
            'engagement' => 0.90,
            'attendance' => 0.93,
            'assignment' => 0.92,
            'streak' => 15,
            'points' => 1520,
            'path' => 'fullstack-python-a-z',
        ],
        'student17@lms.com' => [
            'label' => 'Trung binh - Dang cai thien',
            'level' => 'average',
            'base' => 6.8,
            'spread' => 0.9,
            'strengths' => ['nhập môn', 'nhap mon', 'c++', 'tin học', 'tin hoc'],
            'weaknesses' => ['nâng cao', 'nang cao', 'bảo mật', 'bao mat', 'thuật toán', 'thuat toan'],
            'engagement' => 0.58,
            'attendance' => 0.72,
            'assignment' => 0.55,
            'streak' => 4,
            'points' => 380,
            'path' => 'frontend-vue-nuxt',
        ],
        'student18@lms.com' => [
            'label' => 'Gioi - AI / Python',
            'level' => 'good',
            'base' => 8.4,
            'spread' => 0.7,
            'strengths' => ['python', 'ai', 'machine', 'học máy', 'hoc may', 'dữ liệu', 'du lieu', 'phân tích', 'phan tich', 'thuật toán', 'thuat toan'],
            'weaknesses' => ['mạch', 'mach', 'điện tử', 'dien tu', 'vật lý', 'vat ly'],
            'engagement' => 0.87,
            'attendance' => 0.90,
            'assignment' => 0.90,
            'streak' => 13,
            'points' => 1360,
            'path' => 'fullstack-python-a-z',
        ],
    ];

    public function run(): void
    {
        $currentOnly = (bool) config('demo.student_learning_current_only', false);
        $students = User::query()
            ->where('user_type', 'student')
            ->with(['cohort', 'administrativeClass'])
            ->orderBy('email')
            ->get();

        if ($students->isEmpty()) {
            $this->command?->warn('StudentLearningSeeder: chua co student demo.');

            return;
        }

        $paths = CareerPath::query()->get()->keyBy('slug');
        $instructorId = User::query()->where('user_type', 'instructor')->orderBy('id')->value('id');
        $operationalTerm = $this->resolveOperationalTerm();

        $stats = [
            'students' => 0,
            'grades' => 0,
            'progress' => 0,
            'assignments' => 0,
            'attendance' => 0,
            'attempts' => 0,
            'points' => 0,
            'paths' => 0,
        ];

        foreach ($students as $student) {
            $persona = self::PERSONAS[$student->email] ?? $this->fallbackPersona($student);
            $stats['students']++;

            $enrollments = Enrollment::query()
                ->where('user_id', $student->id)
                ->where(function ($q) {
                    $q->whereNull('enrollment_source')
                        ->orWhere('enrollment_source', '!=', 'marketplace');
                })
                ->with(['course:id,title,status', 'course.lessons', 'term'])
                ->get();

            $currentTermNumber = $this->resolveCurrentTermNumber($student);
            $curriculumId = $student->administrativeClass?->curriculum_id;
            $termByCourseId = $this->termMapForCurriculum($curriculumId);

            foreach ($enrollments as $enrollment) {
                $course = $enrollment->course;
                if (! $course) {
                    continue;
                }

                $termNumber = $termByCourseId[$course->id] ?? null;
                $isPast = $enrollment->term && $operationalTerm
                    ? $enrollment->term->end_date?->lt($operationalTerm->start_date)
                    : ($termNumber !== null && $currentTermNumber !== null
                        ? ((int) $termNumber < (int) $currentTermNumber)
                        : true);
                $isCurrent = $operationalTerm && (int) $enrollment->term_id === (int) $operationalTerm->id;

                if ($currentOnly && ! $isCurrent) {
                    continue;
                }

                $targetScore = $this->scoreForCourse($persona, (string) $course->title, (int) $student->id, (int) $course->id);

                if ($isPast) {
                    $stats['grades'] += $this->seedPastGrades($enrollment, $targetScore, $instructorId);
                } elseif ($isCurrent) {
                    // Ky dang hoc: xoa diem thanh phan de final_score = null
                    // (evaluation / learning-path van coi la "dang hoc")
                    $stats['grades'] += $this->clearCurrentGrades($enrollment);
                }

                if ($isCurrent) {
                    $courseStats = $this->seedCourseActivity($student, $enrollment, $persona, false, $targetScore);
                    $stats['progress'] += $courseStats['progress'];
                    $stats['assignments'] += $courseStats['assignments'];
                    $stats['attendance'] += $courseStats['attendance'];
                    $stats['attempts'] += $courseStats['attempts'];
                }
            }

            $stats['points'] += $this->seedPointsAndStreak($student, $persona);
            $stats['paths'] += $this->seedCareerPath($student, $persona, $paths);
            if (! $currentOnly) {
                $stats['attempts'] += $this->alignExamAttempts($student, $persona);
            }
        }

        $this->command?->info(sprintf(
            'StudentLearningSeeder: %d SV | %d grade entries | %d lesson_progress | %d submissions | %d attendance | %d quiz attempts | %d point txs | %d career paths',
            $stats['students'],
            $stats['grades'],
            $stats['progress'],
            $stats['assignments'],
            $stats['attendance'],
            $stats['attempts'],
            $stats['points'],
            $stats['paths'],
        ));

        if (! $currentOnly) {
            $this->command?->table(
                ['Email', 'Ho ten', 'Persona', 'Level', 'Base', 'Engagement'],
                $students->map(function (User $s) {
                    $p = self::PERSONAS[$s->email] ?? $this->fallbackPersona($s);

                    return [
                        $s->email,
                        $s->name,
                        $p['label'],
                        $p['level'],
                        $p['base'],
                        $p['engagement'],
                    ];
                })->all()
            );
        }
    }

    private function fallbackPersona(User $student): array
    {
        $digits = (int) preg_replace('/\D+/', '', (string) $student->email);
        $n = $digits > 0 ? $digits : (int) sprintf('%u', crc32(strtolower((string) $student->email)));

        return [
            'label' => 'Mac dinh',
            'level' => 'average',
            'base' => 6.5 + ($n % 20) / 10,
            'spread' => 0.8,
            'strengths' => [],
            'weaknesses' => [],
            'engagement' => 0.5,
            'attendance' => 0.7,
            'assignment' => 0.6,
            'streak' => $n % 10,
            'points' => 200 + ($n * 37) % 800,
            'path' => null,
        ];
    }

    private function resolveCurrentTermNumber(User $student): int
    {
        $operationalTerm = $this->resolveOperationalTerm();
        if ($operationalTerm && $student->administrative_class_id) {
            $mapped = AdministrativeClassTerm::query()
                ->where('administrative_class_id', $student->administrative_class_id)
                ->where('term_id', $operationalTerm->id)
                ->value('term_number');
            if ($mapped !== null) {
                return (int) $mapped;
            }
        }

        return 1;
    }

    private function resolveOperationalTerm(): ?Term
    {
        $today = today();

        return Term::query()
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->orderByDesc('is_current')
            ->orderByDesc('start_date')
            ->first()
            ?? Term::query()
                ->whereNotNull('enrollment_start_at')
                ->whereDate('enrollment_start_at', '<=', $today)
                ->whereDate('start_date', '>=', $today)
                ->orderBy('start_date')
                ->first()
            ?? Term::query()
                ->whereDate('start_date', '>=', $today)
                ->orderBy('start_date')
                ->first()
            ?? Term::query()->where('is_current', true)->latest('start_date')->first()
            ?? Term::query()->latest('end_date')->first();
    }

    /**
     * @return array<int, int> course_id => term_number
     */
    private function termMapForCurriculum(?int $curriculumId): array
    {
        if (! $curriculumId) {
            return [];
        }

        return DB::table('curriculum_courses')
            ->where('curriculum_id', $curriculumId)
            ->pluck('term_number', 'course_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    private function scoreForCourse(array $persona, string $title, int $studentId, int $courseId): float
    {
        $normalized = $this->normalize($title);
        $score = (float) $persona['base'];

        foreach ($persona['strengths'] as $kw) {
            if ($kw !== '' && str_contains($normalized, $this->normalize($kw))) {
                $score += 0.9;
                break;
            }
        }

        foreach ($persona['weaknesses'] as $kw) {
            if ($kw !== '' && str_contains($normalized, $this->normalize($kw))) {
                $score -= 1.4;
                break;
            }
        }

        // Noise on dinh theo SV + mon (idempotent, khong random moi lan seed)
        $noise = ((($studentId * 31) + ($courseId * 17)) % 21) / 20 - 0.5; // -0.5 .. +0.5
        $score += $noise * (float) $persona['spread'];

        return round(max(4.0, min(10.0, $score)), 1);
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $map = [
            'à' => 'a', 'á' => 'a', 'ả' => 'a', 'ã' => 'a', 'ạ' => 'a', 'ă' => 'a', 'ằ' => 'a', 'ắ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a', 'ặ' => 'a',
            'â' => 'a', 'ầ' => 'a', 'ấ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a', 'ậ' => 'a',
            'è' => 'e', 'é' => 'e', 'ẻ' => 'e', 'ẽ' => 'e', 'ẹ' => 'e', 'ê' => 'e', 'ề' => 'e', 'ế' => 'e', 'ể' => 'e', 'ễ' => 'e', 'ệ' => 'e',
            'ì' => 'i', 'í' => 'i', 'ỉ' => 'i', 'ĩ' => 'i', 'ị' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ỏ' => 'o', 'õ' => 'o', 'ọ' => 'o', 'ô' => 'o', 'ồ' => 'o', 'ố' => 'o', 'ổ' => 'o', 'ỗ' => 'o', 'ộ' => 'o',
            'ơ' => 'o', 'ờ' => 'o', 'ớ' => 'o', 'ở' => 'o', 'ỡ' => 'o', 'ợ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'ủ' => 'u', 'ũ' => 'u', 'ụ' => 'u', 'ư' => 'u', 'ừ' => 'u', 'ứ' => 'u', 'ử' => 'u', 'ữ' => 'u', 'ự' => 'u',
            'ỳ' => 'y', 'ý' => 'y', 'ỷ' => 'y', 'ỹ' => 'y', 'ỵ' => 'y',
            'đ' => 'd',
        ];

        return strtr($value, $map);
    }

    private function ensureGradeComponents(int $courseId): void
    {
        $template = [
            ['name' => 'Chuyên cần', 'weight' => 10, 'max_score' => 10, 'position' => 1],
            ['name' => 'Giữa kỳ', 'weight' => 30, 'max_score' => 10, 'position' => 2],
            ['name' => 'Cuối kỳ', 'weight' => 60, 'max_score' => 10, 'position' => 3],
        ];

        foreach ($template as $row) {
            GradeComponent::query()->updateOrCreate(
                ['course_id' => $courseId, 'name' => $row['name']],
                array_merge($row, ['course_id' => $courseId, 'is_required' => true]),
            );
        }
    }

    private function seedPastGrades(Enrollment $enrollment, float $finalScore, ?int $gradedBy): int
    {
        $this->ensureGradeComponents((int) $enrollment->course_id);
        $components = GradeComponent::query()->where('course_id', $enrollment->course_id)->get();
        $count = 0;

        foreach ($components as $component) {
            $score = match ($component->name) {
                'Chuyên cần' => min(10, max(6.5, round($finalScore + 0.4, 1))),
                'Giữa kỳ' => round(max(4.0, min(10, $finalScore - 0.3)), 1),
                'Cuối kỳ' => $finalScore,
                default => $finalScore,
            };

            GradeEntry::query()->updateOrCreate(
                [
                    'enrollment_id' => $enrollment->id,
                    'grade_component_id' => $component->id,
                ],
                [
                    'score' => $score,
                    'graded_by' => $gradedBy,
                    'graded_at' => now()->subDays(20 + ($enrollment->id % 40)),
                ]
            );
            $count++;
        }

        return $count;
    }

    private function clearCurrentGrades(Enrollment $enrollment): int
    {
        return (int) GradeEntry::query()
            ->where('enrollment_id', $enrollment->id)
            ->delete();
    }

    /**
     * @return array{progress:int, assignments:int, attendance:int, attempts:int}
     */
    private function seedCourseActivity(User $student, Enrollment $enrollment, array $persona, bool $isPast, float $targetScore): array
    {
        $out = ['progress' => 0, 'assignments' => 0, 'attendance' => 0, 'attempts' => 0];
        $courseId = (int) $enrollment->course_id;

        $lessons = Lesson::query()
            ->where('course_id', $courseId)
            ->orderBy('order')
            ->get();

        if ($lessons->isEmpty()) {
            return $out;
        }

        $engagement = (float) $persona['engagement'];
        // Mon da hoc: complete gan het; mon dang hoc: theo engagement
        $completeRatio = $isPast
            ? min(1.0, max(0.55, $engagement))
            : max(0.08, $engagement * 0.55);

        $completeCount = (int) round($lessons->count() * $completeRatio);
        $completeCount = max(0, min($lessons->count(), $completeCount));

        foreach ($lessons->values() as $index => $lesson) {
            $done = $index < $completeCount;
            $percent = $done ? 100 : (int) max(0, min(95, round(($engagement * 40) + (($index * 7) % 30))));

            // At-risk: kỳ đang học + engagement thấp → không có bài hoàn thành gần đây
            // để báo cáo L&D /advisor at-risk có dữ liệu thật.
            if (! $isPast && $engagement < 0.35) {
                LessonProgress::query()
                    ->where('user_id', $student->id)
                    ->where('lesson_id', $lesson->id)
                    ->delete();

                continue;
            }

            $daysAgo = $isPast
                ? (30 + (($student->id + $lesson->id) % 60))
                : max(0, (int) round((1 - $engagement) * 20) + ($index % 5));

            // Hoc vien tich cuc: updated_at gan day de khong bi at-risk
            if ($engagement >= 0.6) {
                $daysAgo = min($daysAgo, 3 + ($index % 4));
            }

            LessonProgress::query()->updateOrCreate(
                [
                    'user_id' => $student->id,
                    'lesson_id' => $lesson->id,
                ],
                [
                    'progress_percent' => $percent,
                    'watched_seconds' => $done ? (600 + (($lesson->id * 13) % 1800)) : (($lesson->id * 7) % 300),
                    'completed' => $done,
                    'completed_at' => $done ? now()->subDays($daysAgo) : null,
                    'last_watched_at' => now()->subDays(min($daysAgo, 10)),
                    'metadata' => [
                        'seeded_by' => 'StudentLearningSeeder',
                        'persona' => $persona['level'],
                    ],
                ]
            );
            $out['progress']++;

            if ($lesson->type === 'quiz') {
                $quiz = Quiz::query()->where('lesson_id', $lesson->id)->first();
                if ($quiz && ($done || $engagement >= 0.55)) {
                    $quizScore = (int) round(max(35, min(100, ($targetScore / 10) * 100 + (($student->id + $quiz->id) % 8) - 3)));
                    SeededQuizAttempt::upsert(
                        $student->id,
                        $quiz,
                        (float) $quizScore,
                        now()->subDays($daysAgo)->subMinutes(45),
                        now()->subDays($daysAgo),
                    );
                    $out['attempts']++;
                }
            }
        }

        // Assignment submissions
        $assignmentLessons = $lessons->where('type', 'assignment');
        foreach ($assignmentLessons as $aLesson) {
            $assignment = LessonAssignment::query()->where('lesson_id', $aLesson->id)->first();
            if (! $assignment) {
                continue;
            }

            $shouldSubmit = (($student->id + $assignment->id) % 100) < (int) round($persona['assignment'] * 100);
            if (! $shouldSubmit) {
                AssignmentSubmission::query()
                    ->where('user_id', $student->id)
                    ->where('lesson_assignment_id', $assignment->id)
                    ->delete();

                continue;
            }

            $grade = round(max(4.0, min(10.0, $targetScore + ((($student->id + $assignment->id) % 7) - 3) / 10)), 1);
            AssignmentSubmission::query()->updateOrCreate(
                [
                    'user_id' => $student->id,
                    'lesson_assignment_id' => $assignment->id,
                ],
                [
                    'file_url' => 'https://example.com/submissions/'.$student->id.'-'.$assignment->id.'.pdf',
                    'student_note' => 'Nop bai demo - '.$persona['label'],
                    'grade' => $isPast || $persona['engagement'] >= 0.5 ? $grade : null,
                    'feedback' => $isPast ? 'Da cham diem demo.' : null,
                    'submitted_at' => now()->subDays(2 + (($student->id + $assignment->id) % 12)),
                ]
            );
            $out['assignments']++;
        }

        // Attendance: buổi offline + live workshop, QR còn hạn cho kỳ đang học
        $sessionLessons = $lessons->whereIn('type', ['offline', 'live'])->take(3);
        foreach ($sessionLessons->values() as $oi => $oLesson) {
            $isLive = $oLesson->type === 'live';
            $isOpen = ! $isPast;
            $session = OfflineSession::query()->updateOrCreate(
                [
                    'lesson_id' => $oLesson->id,
                    'title' => ($isLive ? 'Live workshop demo - ' : 'Buoi offline demo - ').($enrollment->course?->title ?? $oLesson->title),
                ],
                [
                    'class_section_id' => $enrollment->class_section_id,
                    'course_id' => $enrollment->course_id,
                    'location' => $isLive ? 'Google Meet / Workshop' : 'Phong Lab A'.(1 + ($oi % 3)).' - PTIT',
                    'room' => $isLive ? 'ONLINE' : 'A'.(201 + $oi),
                    'start_at' => $isOpen
                        ? ($isLive ? now()->setTime(19, 30) : now()->subMinutes(15))
                        : now()->subDays(5 + $oi * 7)->setTime(8, 30),
                    'duration' => $isLive ? 90 : 120,
                    'max_participants' => 40,
                    'latitude' => $isOpen ? null : 20.9808,
                    'longitude' => $isOpen ? null : 105.7874,
                    'check_in_radius_meters' => $isOpen ? OfflineSession::DEFAULT_CHECK_IN_RADIUS_METERS : 50,
                    'is_active' => $isOpen,
                    'qr_enabled' => true,
                    'qr_mode' => OfflineSession::QR_MODE_MANUAL,
                    'qr_token' => Str::random(48),
                    'qr_expires_at' => $isOpen ? now()->addDays(14) : now()->subDays(4 + $oi * 7),
                ]
            );
            if ($isOpen) {
                $session->generateQrToken(14 * 24 * 60);
            }

            $demoLogin = preg_match('/^student(?:[1-9]|1[0-6])@lms\.com$/i', (string) $student->email);
            if ($isOpen && $demoLogin) {
                OfflineSessionAttendance::query()
                    ->where('user_id', $student->id)
                    ->where('offline_session_id', $session->id)
                    ->delete();
                continue;
            }

            $present = (($student->id + $session->id) % 100) < (int) round($persona['attendance'] * 100);
            if (! $present) {
                OfflineSessionAttendance::query()
                    ->where('user_id', $student->id)
                    ->where('offline_session_id', $session->id)
                    ->delete();

                continue;
            }

            $late = $persona['attendance'] < 0.75 && (($student->id + $session->id) % 3 === 0);
            OfflineSessionAttendance::query()->updateOrCreate(
                [
                    'user_id' => $student->id,
                    'offline_session_id' => $session->id,
                ],
                [
                    'status' => $late ? 'late' : 'present',
                    'checked_in_at' => $session->start_at?->copy()->addMinutes($late ? 20 : 5),
                    'device_info' => 'seed-demo',
                    'latitude' => 20.9808,
                    'longitude' => 105.7874,
                    'distance_meters' => 5 + (($student->id + $session->id) % 20),
                ]
            );
            $out['attendance']++;
        }

        return $out;
    }

    private function seedPointsAndStreak(User $student, array $persona): int
    {
        PointTransaction::query()
            ->where('user_id', $student->id)
            ->where('description', 'like', '% (seed)')
            ->delete();

        $target = (int) $persona['points'];
        $created = 0;
        $earned = 0;

        $actions = [
            ['login_daily', 'Diem danh hang ngay', PointService::POINTS['login_daily']],
            ['lesson_complete', 'Hoan thanh bai hoc', PointService::POINTS['lesson_complete']],
            ['exam_high_score', 'Diem thi cao', PointService::POINTS['exam_high_score']],
            ['review_course', 'Danh gia khoa hoc', PointService::POINTS['review_course']],
            ['course_complete', 'Hoan thanh khoa hoc', PointService::POINTS['course_complete']],
        ];

        $day = 1;
        while ($earned < $target && $day < 200) {
            $action = $actions[$day % count($actions)];
            $amount = (int) $action[2];
            if ($earned + $amount > $target + 50) {
                $amount = max(1, $target - $earned);
            }

            PointTransaction::query()->create([
                'user_id' => $student->id,
                'type' => 'earn',
                'action' => $action[0],
                'amount' => $amount,
                'description' => $action[1].' (seed)',
                'created_at' => now()->subDays($day),
                'updated_at' => now()->subDays($day),
            ]);
            $earned += $amount;
            $created++;
            $day++;
        }

        $student->forceFill([
            'points_balance' => $earned,
            'streak_days' => (int) $persona['streak'],
            'last_login_date' => $persona['streak'] > 0 ? now()->toDateString() : now()->subDays(10)->toDateString(),
        ])->save();

        return $created;
    }

    private function alignExamAttempts(User $student, array $persona): int
    {
        $attempts = QuizAttempt::query()
            ->where('user_id', $student->id)
            ->whereHas('quiz', fn ($q) => $q->whereNotNull('exam_id'))
            ->with('quiz:id,exam_id,pass_score')
            ->get();

        $updated = 0;
        foreach ($attempts as $attempt) {
            $base = (int) round(($persona['base'] / 10) * 100);
            $noise = (($student->id + (int) $attempt->quiz_id) % 11) - 5;
            $score = max(30, min(100, $base + $noise));

            // At-risk: mot so bai khong nop
            if ($persona['level'] === 'struggling' && (($student->id + $attempt->quiz_id) % 3 === 0)) {
                $attempt->delete();

                continue;
            }

            $attempt->update([
                'status' => 'submitted',
                'score' => $score,
                'passed' => $score >= (int) ($attempt->quiz?->pass_score ?? 50),
            ]);
            $updated++;
        }

        return $updated;
    }

    private function seedCareerPath(User $student, array $persona, $paths): int
    {
        $slug = $persona['path'] ?? null;
        if (! $slug || ! $paths->has($slug)) {
            return 0;
        }

        $path = $paths->get($slug);
        $progress = (int) round(min(95, max(10, $persona['engagement'] * 100)));

        UserCareerPath::query()->updateOrCreate(
            [
                'user_id' => $student->id,
                'career_path_id' => $path->id,
            ],
            [
                'status' => $progress >= 90 ? 'completed' : 'in_progress',
                'progress_percent' => $progress,
                'started_at' => now()->subDays(40),
                'completed_at' => $progress >= 90 ? now()->subDays(2) : null,
            ]
        );

        return 1;
    }
}
