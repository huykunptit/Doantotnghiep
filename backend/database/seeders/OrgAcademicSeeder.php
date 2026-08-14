<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\AdministrativeClass;
use App\Models\Cohort;
use App\Models\Curriculum;
use App\Models\Institution;
use App\Models\Major;
use App\Models\Position;
use App\Models\Program;
use App\Models\ProgramType;
use App\Models\Term;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserAssignment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seed cấu trúc tổ chức + học vụ theo schema mới:
 *  - Khoa: CNTT1, QTKD1, Viễn thông 1, Cơ bản 1, Thuê ngoài
 *  - 3 chương trình: CNTT, QTKD, ĐTVT (đều chính quy, 8 kỳ)
 *  - Mỗi chương trình có curriculum + cohort demo D23/D24 (năm học 2025–2026)
 *  - Lớp hành chính: 2 lớp / cohort (vd D23CN01, D23CN02…)
 *  - Cập nhật profile SV/GV
 *
 * Lưu ý: users gốc (admin/instructor/student) được tạo trong DatabaseSeeder
 * BEFORE seeder này chạy — seeder này chỉ cập nhật profile + gán LHC.
 */
class OrgAcademicSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::query()->updateOrCreate(
            ['code' => 'PTIT'],
            ['name' => 'Học viện Công nghệ Bưu chính Viễn thông', 'institution_type' => 'university', 'is_active' => true]
        );

        $units = $this->seedFaculties($institution->id);
        $positions = $this->seedPositions();
        $programContext = $this->seedPrograms($institution->id, $units);
        $terms = $this->seedAcademicCalendar($institution->id);
        $adminClasses = $this->seedAdministrativeClasses($institution->id, $units, $programContext);

        $this->seedUserProfilesAndAssignments($institution->id, $units, $positions, $programContext, $adminClasses, $terms);
    }

    private function seedFaculties(int $institutionId): array
    {
        $board = Unit::query()->updateOrCreate(
            ['institution_id' => $institutionId, 'code' => 'BGH'],
            ['name' => 'Ban Giám hiệu', 'unit_type' => 'board', 'parent_id' => null, 'level' => 1, 'is_active' => true]
        );

        $academicOffice = Unit::query()->updateOrCreate(
            ['institution_id' => $institutionId, 'code' => 'PDT'],
            ['name' => 'Phòng Đào tạo', 'unit_type' => 'office', 'parent_id' => $board->id, 'level' => 2, 'is_active' => true]
        );

        $faculties = [
            'cntt1' => ['code' => 'CNTT1', 'name' => 'Khoa Công nghệ thông tin 1'],
            'qtkd1' => ['code' => 'QTKD1', 'name' => 'Khoa Quản trị kinh doanh 1'],
            'vt1' => ['code' => 'VT1', 'name' => 'Khoa Viễn thông 1'],
            'cb1' => ['code' => 'CB1', 'name' => 'Khoa Cơ bản 1'],
            'external' => ['code' => 'THUENGOAI', 'name' => 'Khoa Thuê ngoài'],
        ];

        $created = [];
        foreach ($faculties as $key => $row) {
            $created[$key] = Unit::query()->updateOrCreate(
                ['institution_id' => $institutionId, 'code' => $row['code']],
                ['name' => $row['name'], 'unit_type' => 'faculty', 'parent_id' => $board->id, 'level' => 2, 'is_active' => true]
            );
        }

        return array_merge([
            'board' => $board,
            'academicOffice' => $academicOffice,
        ], $created);
    }

    private function seedPositions(): array
    {
        $admin = Position::query()->updateOrCreate(
            ['code' => 'SYSTEM_ADMIN'],
            ['name' => 'Quản trị hệ thống', 'scope_level' => 'institution', 'description' => 'Quản trị toàn bộ hệ thống', 'is_active' => true]
        );
        $facultyAdmin = Position::query()->updateOrCreate(
            ['code' => 'FACULTY_ADMIN'],
            ['name' => 'Quản lý khoa', 'scope_level' => 'unit', 'description' => 'Quản lý học vụ cấp khoa', 'is_active' => true]
        );
        $academicManager = Position::query()->updateOrCreate(
            ['code' => 'ACADEMIC_MANAGER'],
            ['name' => 'Quản lý học tập', 'scope_level' => 'institution', 'description' => 'Phụ trách CTĐT, lớp hành chính, lớp tín chỉ', 'is_active' => true]
        );
        $lecturer = Position::query()->updateOrCreate(
            ['code' => 'LECTURER'],
            ['name' => 'Giảng viên', 'scope_level' => 'unit', 'description' => 'Giảng dạy và theo dõi lớp học phần', 'is_active' => true]
        );
        $student = Position::query()->updateOrCreate(
            ['code' => 'STUDENT'],
            ['name' => 'Sinh viên', 'scope_level' => 'cohort', 'description' => 'Sinh viên thuộc chương trình đào tạo', 'is_active' => true]
        );

        return compact('admin', 'facultyAdmin', 'academicManager', 'lecturer', 'student');
    }

    private function seedPrograms(int $institutionId, array $units): array
    {
        $formal = ProgramType::query()->updateOrCreate(
            ['code' => 'CHINH_QUY'],
            ['name' => 'Chính quy', 'is_active' => true]
        );

        ProgramType::query()->updateOrCreate(
            ['code' => 'TU_XA'],
            ['name' => 'Từ xa', 'is_active' => true]
        );
        ProgramType::query()->updateOrCreate(
            ['code' => 'VHVL'],
            ['name' => 'Vừa học vừa làm', 'is_active' => true]
        );
        ProgramType::query()->updateOrCreate(
            ['code' => 'LIEN_KET_DN'],
            ['name' => 'Liên kết doanh nghiệp', 'is_active' => true]
        );
        ProgramType::query()->updateOrCreate(
            ['code' => 'LIEN_THONG'],
            ['name' => 'Liên thông', 'is_active' => true]
        );
        ProgramType::query()->updateOrCreate(
            ['code' => 'CHAT_LUONG_CAO'],
            ['name' => 'Chất lượng cao', 'is_active' => true]
        );

        // BA 2026: gom còn 3 CHUYÊN NGÀNH chính (= 3 nhóm nghề nghiệp).
        // Mỗi chương trình = 1 chuyên ngành = 1 CTĐT (bỏ tầng 9 major cũ).
        // major code = program code để giữ tương thích FK users.major_id.
        $programDefs = [
            'cntt' => [
                'code' => 'CNTT',
                'name' => 'Công nghệ thông tin',
                'unit' => $units['cntt1'],
                'majors' => [
                    'CNTT' => 'Công nghệ thông tin',
                ],
            ],
            'qtkd' => [
                'code' => 'QTKD',
                'name' => 'Quản trị kinh doanh',
                'unit' => $units['qtkd1'],
                'majors' => [
                    'QTKD' => 'Quản trị kinh doanh',
                ],
            ],
            'dtvt' => [
                'code' => 'DTVT',
                'name' => 'Kỹ thuật Điện tử Viễn thông',
                'unit' => $units['vt1'],
                'majors' => [
                    'DTVT' => 'Kỹ thuật Điện tử Viễn thông',
                ],
            ],
        ];

        $programs = [];
        $majorsByProg = [];   // [$progKey][$majorCode] => Major
        $curriculaByMajor = []; // [$majorCode] => Curriculum

        foreach ($programDefs as $progKey => $def) {
            $program = Program::query()->updateOrCreate(
                ['institution_id' => $institutionId, 'code' => $def['code']],
                [
                    'unit_id' => $def['unit']->id,
                    'program_type_id' => $formal->id,
                    'name' => $def['name'],
                    'duration_months' => 48,
                    'is_active' => true,
                ]
            );
            $programs[$progKey] = $program;

            foreach ($def['majors'] as $majorCode => $majorName) {
                $major = Major::query()->updateOrCreate(
                    ['program_id' => $program->id, 'code' => $majorCode],
                    ['unit_id' => $def['unit']->id, 'name' => $majorName, 'is_active' => true]
                );
                $majorsByProg[$progKey][$majorCode] = $major;

                // Mỗi chuyên ngành có 1 curriculum riêng (CTĐT 2024)
                $curriculum = Curriculum::query()->updateOrCreate(
                    ['program_id' => $program->id, 'code' => 'CTDT-' . $majorCode],
                    [
                        'major_id' => $major->id,
                        'name' => 'CTĐT ' . $majorName . ' - 2024',
                        'effective_from' => '2024-08-01',
                        'is_active' => true,
                    ]
                );
                $curriculaByMajor[$majorCode] = $curriculum;
            }
        }

        // Cohorts demo: chỉ D23 + D24 (đang học năm 2025–2026) — bỏ D22/D25 cho nhẹ data
        $cohorts = [];
        foreach (['cntt', 'qtkd', 'dtvt'] as $progKey) {
            foreach ([2023, 2024] as $year) {
                $shortYear = substr((string) $year, 2);
                $code = 'D' . $shortYear . strtoupper($progKey);
                $cohort = Cohort::query()->updateOrCreate(
                    ['program_id' => $programs[$progKey]->id, 'code' => $code],
                    [
                        'institution_id' => $institutionId,
                        'major_id' => null,
                        'name' => 'Khóa D' . $shortYear . ' - ' . $programs[$progKey]->name,
                        'start_year' => $year,
                        'end_year' => $year + 4,
                        'status' => 'active',
                    ]
                );
                $cohorts[$progKey][$year] = $cohort;
            }
        }

        return array_merge($programs, [
            'majors' => $majorsByProg,
            'curricula' => $curriculaByMajor,
            'cohorts' => $cohorts,
            'formal' => $formal,
            'demo_years' => [2023, 2024],
        ]);
    }

    private function seedAcademicCalendar(int $institutionId): array
    {
        // Seed đủ năm học cho các khoá demo D23/D24 (2023→2028+) — mỗi năm 2 học kỳ.
        $years = [];
        $firstTerm = null;
        $secondTerm = null;

        for ($yStart = 2023; $yStart <= 2028; $yStart++) {
            $yEnd = $yStart + 1;
            $name = "{$yStart}-{$yEnd}";
            $year = AcademicYear::query()->updateOrCreate(
                ['institution_id' => $institutionId, 'name' => $name],
                [
                    'start_date' => sprintf('%d-08-01', $yStart),
                    'end_date' => sprintf('%d-07-31', $yEnd),
                    'is_current' => $name === '2025-2026',
                    'status' => 'active',
                ]
            );
            $years[$name] = $year;

            $term1 = Term::query()->updateOrCreate(
                ['academic_year_id' => $year->id, 'code' => 'HK1'],
                [
                    'name' => 'Học kỳ 1',
                    'start_date' => sprintf('%d-08-01', $yStart),
                    'end_date' => sprintf('%d-01-15', $yEnd),
                    'enrollment_start_at' => sprintf('%d-07-15 00:00:00', $yStart),
                    'enrollment_end_at' => sprintf('%d-08-05 23:59:59', $yStart),
                    'exam_start_at' => sprintf('%d-12-15 00:00:00', $yStart),
                    'exam_end_at' => sprintf('%d-01-15 23:59:59', $yEnd),
                    'is_current' => $name === '2025-2026',
                    'status' => 'active',
                ]
            );
            $term2 = Term::query()->updateOrCreate(
                ['academic_year_id' => $year->id, 'code' => 'HK2'],
                [
                    'name' => 'Học kỳ 2',
                    'start_date' => sprintf('%d-02-01', $yEnd),
                    'end_date' => sprintf('%d-06-30', $yEnd),
                    'is_current' => false,
                    'status' => 'active',
                ]
            );

            if ($name === '2025-2026') {
                $firstTerm = $term1;
                $secondTerm = $term2;
            }
        }

        AcademicYear::query()
            ->where('institution_id', $institutionId)
            ->where('name', '!=', '2025-2026')
            ->update(['is_current' => false]);

        $year = $years['2025-2026'] ?? reset($years);

        return [
            'year' => $year,
            'term1' => $firstTerm ?? Term::query()->where('code', 'HK1')->first(),
            'term2' => $secondTerm ?? Term::query()->where('code', 'HK2')->first(),
        ];
    }

    /**
     * LHC mẫu: với mỗi cohort, tạo 2 lớp (vd D22CN01, D22CN02).
     * Lớp hành chính gom SV của cả 3 chuyên ngành cùng program — major_id = null.
     */
    private function seedAdministrativeClasses(int $institutionId, array $units, array $programContext): array
    {
        $progToFaculty = [
            'cntt' => $units['cntt1'],
            'qtkd' => $units['qtkd1'],
            'dtvt' => $units['vt1'],
        ];
        $progShortCode = [
            'cntt' => 'CN',
            'qtkd' => 'QTKD',
            'dtvt' => 'DTVT',
        ];
        // Mỗi chuyên ngành có đúng 1 CTĐT (curriculum code = CTDT-{programCode})
        $progDefaultCurriculumKey = [
            'cntt' => 'CNTT',
            'qtkd' => 'QTKD',
            'dtvt' => 'DTVT',
        ];

        $result = [];
        $demoYears = $programContext['demo_years'] ?? [2023, 2024];

        foreach (['cntt', 'qtkd', 'dtvt'] as $progKey) {
            $program = $programContext[$progKey];
            $faculty = $progToFaculty[$progKey];
            $curriculumKey = $progDefaultCurriculumKey[$progKey];
            $curriculum = $programContext['curricula'][$curriculumKey] ?? null;

            foreach ($demoYears as $startYear) {
                $cohort = $programContext['cohorts'][$progKey][$startYear] ?? null;
                if (!$cohort) {
                    continue;
                }
                $shortYear = substr((string) $startYear, 2);
                $perCohort = 2;

                for ($i = 1; $i <= $perCohort; $i++) {
                    $code = sprintf('D%s%s%02d', $shortYear, $progShortCode[$progKey], $i);
                    $admin = AdministrativeClass::query()->updateOrCreate(
                        ['institution_id' => $institutionId, 'code' => $code],
                        [
                            'unit_id' => $faculty->id,
                            'program_id' => $program->id,
                            'major_id' => null,
                            'cohort_id' => $cohort->id,
                            'advisor_id' => null,
                            'curriculum_id' => $curriculum?->id,
                            'name' => 'Lớp ' . $code,
                            'expected_graduation_year' => $startYear + 4,
                            'capacity' => 40,
                            'status' => 'active',
                            'description' => "Lớp hành chính demo {$code} (năm học hiện tại).",
                        ]
                    );
                    $result[$progKey][$startYear][] = $admin;
                }
            }
        }

        return $result;
    }

    private function seedUserProfilesAndAssignments(
        int $institutionId,
        array $units,
        array $positions,
        array $programContext,
        array $adminClasses,
        array $terms
    ): void {
        $this->seedAcademicManagers($institutionId, $units, $positions);

        $admin = User::query()->where('email', 'admin@lms.com')->first();
        if ($admin) {
            $admin->update([
                'institution_id' => $institutionId,
                'unit_id' => $units['board']->id,
                'user_type' => 'admin',
                'staff_code' => 'ADM001',
                'phone' => '0900000001',
                'gender' => 'male',
                'date_of_birth' => '1985-01-01',
                'nationality' => 'Việt Nam',
                'hometown' => 'Hà Nội',
                'permanent_address' => 'Hà Đông, Hà Nội',
                'study_status' => 'dang_cong_tac',
                'id_card_number' => '001085000001',
            ]);
            $this->upsertAssignment($admin->id, $units['board']->id, $positions['admin']->id, true);
        }

        $hometowns = ['Hà Nội', 'TP. Hồ Chí Minh', 'Đà Nẵng', 'Hải Phòng', 'Nghệ An', 'Thanh Hóa', 'Nam Định', 'Bắc Ninh'];
        $facultyKeys = ['cntt1', 'qtkd1', 'vt1', 'cb1', 'external'];

        // Giảng viên: phân bổ tuần tự vào 5 khoa
        $instructors = User::query()->where('email', 'like', 'instructor%@lms.com')->orderBy('id')->get();
        $instructors->each(function (User $user, int $index) use ($institutionId, $units, $positions, $facultyKeys, $hometowns) {
            $facultyKey = $facultyKeys[$index % count($facultyKeys)];
            $unit = $units[$facultyKey];
            $staffCode = 'GV' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);

            $user->update([
                'institution_id' => $institutionId,
                'unit_id' => $unit->id,
                'user_type' => 'instructor',
                'staff_code' => $staffCode,
                'phone' => '0910000' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'gender' => $index % 2 === 0 ? 'male' : 'female',
                'date_of_birth' => sprintf('19%02d-%02d-%02d', 80 + ($index % 10), ($index % 12) + 1, ($index % 28) + 1),
                'nationality' => 'Việt Nam',
                'hometown' => $hometowns[$index % count($hometowns)],
                'permanent_address' => 'Số ' . ($index + 1) . ' đường Cầu Giấy, Hà Nội',
                'id_card_number' => '00' . str_pad((string) (90000000 + $index), 10, '0', STR_PAD_LEFT),
                'study_status' => 'dang_cong_tac',
            ]);

            $user->syncRoles(['instructor']);
            $this->upsertAssignment($user->id, $unit->id, $positions['lecturer']->id, true);

            // Nửa đầu cũng kiêm cố vấn
            if ($index < (int) ceil($user->newQuery()->where('email', 'like', 'instructor%@lms.com')->count() / 2)) {
                $user->assignRole('advisor');
            }
        });

        // Gán advisor cho LHC: lấy giảng viên cùng khoa nếu có
        $this->assignAdvisorsToAdminClasses($adminClasses, $instructors, $units);

        $advisorPool = $instructors->take((int) ceil($instructors->count() / 2))->values();

        // Sinh viên: phân bổ vào LHC theo round-robin trong cohort thuộc CNTT (giữ tương thích seeder cũ),
        // mở rộng dần để có dữ liệu mẫu cho cả 3 chương trình.
        $allAdminClasses = collect();
        $demoYears = $programContext['demo_years'] ?? [2023, 2024];
        foreach (['cntt', 'qtkd', 'dtvt'] as $progKey) {
            foreach ($demoYears as $startYear) {
                foreach (($adminClasses[$progKey][$startYear] ?? []) as $class) {
                    $allAdminClasses->push(['prog' => $progKey, 'year' => $startYear, 'class' => $class]);
                }
            }
        }

        // Round-robin chuyên ngành cho student trong từng program
        $progMajorRotation = [
            'cntt' => array_values($programContext['majors']['cntt']),
            'qtkd' => array_values($programContext['majors']['qtkd']),
            'dtvt' => array_values($programContext['majors']['dtvt']),
        ];
        $progMajorCounter = ['cntt' => 0, 'qtkd' => 0, 'dtvt' => 0];
        $usedStudentCodes = [];

        User::role('student')->orderBy('id')->get()->each(function (User $user, int $index) use ($institutionId, $units, $positions, $programContext, $allAdminClasses, $advisorPool, $hometowns, $progMajorRotation, &$progMajorCounter, &$usedStudentCodes) {
            $entry = $allAdminClasses[$index % $allAdminClasses->count()];
            $progKey = $entry['prog'];
            $startYear = $entry['year'];
            $adminClass = $entry['class'];
            $program = $programContext[$progKey];
            $majorList = $progMajorRotation[$progKey];
            $major = $majorList[$progMajorCounter[$progKey]++ % count($majorList)];
            $cohort = $programContext['cohorts'][$progKey][$startYear];

            // Mã SV: B + khóa (từ năm nhập học / lớp D23…) + DV + CN|QT|DT + 3 số ngẫu nhiên unique
            $shortYear = substr((string) $startYear, 2);
            $majorAbbrev = match ($progKey) {
                'cntt' => 'CN',
                'qtkd' => 'QT',
                'dtvt' => 'DT',
                default => 'CN',
            };
            $codePrefix = 'B' . $shortYear . 'DV' . $majorAbbrev;
            $currentCode = strtoupper(trim((string) $user->student_code));
            $canKeepCurrentCode = str_starts_with($currentCode, $codePrefix)
                && !isset($usedStudentCodes[$currentCode])
                && !User::query()
                    ->where('student_code', $currentCode)
                    ->where('id', '!=', $user->id)
                    ->exists();
            $studentCode = $canKeepCurrentCode
                ? $currentCode
                : $this->generateUniqueStudentCode($codePrefix, $usedStudentCodes, $user->id);
            $usedStudentCodes[$studentCode] = true;
            // Giữ student1@lms.com … student16@lms.com cho 16 SV demo gốc (theo tên trong UserSeeder)
            $demoNames = array_slice(\Database\Seeders\UserSeeder::studentNames(), 0, \Database\Seeders\UserSeeder::DEMO_LMS_STUDENT_COUNT);
            $demoIndex = array_search($user->name, $demoNames, true);
            $studentEmail = $demoIndex !== false
                ? ('student' . ($demoIndex + 1) . '@lms.com')
                : (strtolower($studentCode) . '@stu.ptit.edu.vn');
            $advisor = $advisorPool->isEmpty() ? null : $advisorPool[$index % $advisorPool->count()];

            $user->update([
                'email' => $studentEmail,
                'institution_id' => $institutionId,
                'unit_id' => $adminClass->unit_id,
                'program_id' => $program->id,
                'major_id' => $major->id,
                'specialization_id' => null,
                'cohort_id' => $cohort->id,
                'administrative_class_id' => $adminClass->id,
                'advisor_id' => $advisor?->id,
                'user_type' => 'student',
                'student_code' => $studentCode,
                'phone' => '0980000' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'gender' => $index % 2 === 0 ? 'male' : 'female',
                'date_of_birth' => sprintf('%04d-%02d-%02d', $startYear - 18, ($index % 12) + 1, ($index % 28) + 1),
                'nationality' => 'Việt Nam',
                'hometown' => $hometowns[$index % count($hometowns)],
                'permanent_address' => 'Số ' . ($index + 10) . ' đường Nguyễn Trãi, Hà Nội',
                'id_card_number' => '00' . str_pad((string) (100000000 + $index), 10, '0', STR_PAD_LEFT),
                'study_status' => 'dang_hoc',
            ]);
            $this->upsertAssignment($user->id, $adminClass->unit_id, $positions['student']->id, true);
        });
    }

    /**
     * Sinh mã SV không trùng: {prefix}{000-999}, ví dụ B23DVCN847.
     *
     * @param  array<string, true>  $used
     */
    private function generateUniqueStudentCode(string $prefix, array &$used, ?int $exceptUserId = null): string
    {
        for ($attempt = 0; $attempt < 1000; $attempt++) {
            // Deterministic by user id so repeated demo seeds keep the same student code.
            $number = (((int) $exceptUserId * 97) + $attempt) % 1000;
            $suffix = str_pad((string) $number, 3, '0', STR_PAD_LEFT);
            $code = $prefix . $suffix;
            if (isset($used[$code])) {
                continue;
            }
            $query = User::query()->where('student_code', $code);
            if ($exceptUserId) {
                $query->where('id', '!=', $exceptUserId);
            }
            if ($query->exists()) {
                continue;
            }
            $used[$code] = true;

            return $code;
        }

        throw new \RuntimeException("Không sinh được mã sinh viên unique cho prefix {$prefix}");
    }

    /**
     * Tạo 2 user role academic_manager (quản lý học tập).
     */
    private function seedAcademicManagers(int $institutionId, array $units, array $positions): void
    {
        $managers = [
            ['email' => 'academic1@lms.com', 'name' => 'ThS. Nguyễn Thị Hồng Nhung', 'staff_code' => 'QLHT001'],
            ['email' => 'academic2@lms.com', 'name' => 'ThS. Phạm Văn Thành', 'staff_code' => 'QLHT002'],
        ];

        foreach ($managers as $idx => $row) {
            $user = User::query()->updateOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $row['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'avatar' => 'https://i.pravatar.cc/300?img=' . (30 + $idx),
                    'institution_id' => $institutionId,
                    'unit_id' => $units['academicOffice']->id,
                    'user_type' => 'academic_manager',
                    'staff_code' => $row['staff_code'],
                    'phone' => '0920000' . str_pad((string) ($idx + 1), 3, '0', STR_PAD_LEFT),
                    'gender' => $idx % 2 === 0 ? 'female' : 'male',
                    'date_of_birth' => '1988-0' . ($idx + 5) . '-12',
                    'nationality' => 'Việt Nam',
                    'hometown' => 'Hà Nội',
                    'permanent_address' => 'Khu Trung Hòa, Cầu Giấy, Hà Nội',
                    'id_card_number' => '001088' . str_pad((string) ($idx + 1), 6, '0', STR_PAD_LEFT),
                    'study_status' => 'dang_cong_tac',
                ]
            );
            $user->syncRoles(['academic_manager']);
            $this->upsertAssignment($user->id, $units['academicOffice']->id, $positions['academicManager']->id, true);
        }
    }

    private function assignAdvisorsToAdminClasses(array $adminClasses, $instructors, array $units): void
    {
        if ($instructors->isEmpty()) {
            return;
        }

        $byUnit = $instructors->groupBy('unit_id');
        $flatClasses = [];
        foreach ($adminClasses as $progKey => $byYear) {
            foreach ($byYear as $year => $classes) {
                foreach ($classes as $c) {
                    $flatClasses[] = $c;
                }
            }
        }

        foreach ($flatClasses as $i => $class) {
            $candidates = $byUnit->get($class->unit_id, collect());
            $advisor = $candidates->isEmpty() ? $instructors[$i % $instructors->count()] : $candidates[$i % $candidates->count()];
            $class->update(['advisor_id' => $advisor->id]);
        }
    }

    private function upsertAssignment(int $userId, int $unitId, int $positionId, bool $isPrimary): void
    {
        UserAssignment::query()->updateOrCreate(
            ['user_id' => $userId, 'unit_id' => $unitId, 'position_id' => $positionId],
            ['is_primary' => $isPrimary, 'status' => 'active', 'start_date' => now()->toDateString(), 'end_date' => null]
        );
    }
}
