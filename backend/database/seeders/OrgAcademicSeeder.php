<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Cohort;
use App\Models\Institution;
use App\Models\Major;
use App\Models\Position;
use App\Models\Program;
use App\Models\ProgramType;
use App\Models\Specialization;
use App\Models\Term;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserAssignment;
use Illuminate\Database\Seeder;

class OrgAcademicSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::query()->updateOrCreate(
            ['code' => 'PTIT'],
            ['name' => 'Học viện Công nghệ Bưu chính Viễn thông', 'institution_type' => 'university', 'is_active' => true]
        );

        $units = $this->seedUnits($institution->id);
        $positions = $this->seedPositions();
        $programContext = $this->seedPrograms($institution->id, $units);
        $terms = $this->seedAcademicCalendar($institution->id);

        $this->seedUserProfilesAndAssignments($institution->id, $units, $positions, $programContext, $terms);
    }

    private function seedUnits(int $institutionId): array
    {
        $board = Unit::query()->updateOrCreate(
            ['institution_id' => $institutionId, 'code' => 'BGH'],
            ['name' => 'Ban Giám hiệu', 'unit_type' => 'board', 'parent_id' => null, 'level' => 1, 'is_active' => true]
        );

        $academicOffice = Unit::query()->updateOrCreate(
            ['institution_id' => $institutionId, 'code' => 'PDT'],
            ['name' => 'Phòng Đào tạo', 'unit_type' => 'office', 'parent_id' => $board->id, 'level' => 2, 'is_active' => true]
        );

        $itFaculty = Unit::query()->updateOrCreate(
            ['institution_id' => $institutionId, 'code' => 'CNTT'],
            ['name' => 'Khoa Công nghệ thông tin', 'unit_type' => 'faculty', 'parent_id' => $board->id, 'level' => 2, 'is_active' => true]
        );

        $bizFaculty = Unit::query()->updateOrCreate(
            ['institution_id' => $institutionId, 'code' => 'QTKD'],
            ['name' => 'Khoa Quản trị kinh doanh', 'unit_type' => 'faculty', 'parent_id' => $board->id, 'level' => 2, 'is_active' => true]
        );

        $seDept = Unit::query()->updateOrCreate(
            ['institution_id' => $institutionId, 'code' => 'BMSE'],
            ['name' => 'Bộ môn Kỹ thuật phần mềm', 'unit_type' => 'department', 'parent_id' => $itFaculty->id, 'level' => 3, 'is_active' => true]
        );

        $dsDept = Unit::query()->updateOrCreate(
            ['institution_id' => $institutionId, 'code' => 'BMDS'],
            ['name' => 'Bộ môn Khoa học dữ liệu', 'unit_type' => 'department', 'parent_id' => $itFaculty->id, 'level' => 3, 'is_active' => true]
        );

        return compact('board', 'academicOffice', 'itFaculty', 'bizFaculty', 'seDept', 'dsDept');
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
        $lecturer = Position::query()->updateOrCreate(
            ['code' => 'LECTURER'],
            ['name' => 'Giảng viên', 'scope_level' => 'unit', 'description' => 'Giảng dạy và theo dõi lớp học phần', 'is_active' => true]
        );
        $student = Position::query()->updateOrCreate(
            ['code' => 'STUDENT'],
            ['name' => 'Sinh viên', 'scope_level' => 'cohort', 'description' => 'Sinh viên thuộc chương trình đào tạo', 'is_active' => true]
        );

        return compact('admin', 'facultyAdmin', 'lecturer', 'student');
    }

    private function seedPrograms(int $institutionId, array $units): array
    {
        $formal = ProgramType::query()->updateOrCreate(['code' => 'CHINH_QUY'], ['name' => 'Chính quy', 'is_active' => true]);
        $partTime = ProgramType::query()->updateOrCreate(['code' => 'VLVH'], ['name' => 'Vừa làm vừa học', 'is_active' => true]);
        $enterprise = ProgramType::query()->updateOrCreate(['code' => 'LKDN'], ['name' => 'Liên kết doanh nghiệp', 'is_active' => true]);

        $itProgram = Program::query()->updateOrCreate(
            ['institution_id' => $institutionId, 'code' => 'CNTT_CQ'],
            [
                'unit_id' => $units['itFaculty']->id,
                'program_type_id' => $formal->id,
                'name' => 'Công nghệ thông tin - Chính quy',
                'duration_months' => 48,
                'is_active' => true,
            ]
        );

        $baProgram = Program::query()->updateOrCreate(
            ['institution_id' => $institutionId, 'code' => 'QTKD_VLVH'],
            [
                'unit_id' => $units['bizFaculty']->id,
                'program_type_id' => $partTime->id,
                'name' => 'Quản trị kinh doanh - Vừa làm vừa học',
                'duration_months' => 48,
                'is_active' => true,
            ]
        );

        $seMajor = Major::query()->updateOrCreate(
            ['program_id' => $itProgram->id, 'code' => 'KTPM'],
            ['unit_id' => $units['seDept']->id, 'name' => 'Kỹ thuật phần mềm', 'is_active' => true]
        );
        $dsMajor = Major::query()->updateOrCreate(
            ['program_id' => $itProgram->id, 'code' => 'KHDT'],
            ['unit_id' => $units['dsDept']->id, 'name' => 'Khoa học dữ liệu', 'is_active' => true]
        );

        $webSpec = Specialization::query()->updateOrCreate(
            ['major_id' => $seMajor->id, 'code' => 'WEB'],
            ['name' => 'Phát triển ứng dụng Web', 'is_active' => true]
        );
        $aiSpec = Specialization::query()->updateOrCreate(
            ['major_id' => $dsMajor->id, 'code' => 'AIAPP'],
            ['name' => 'Ứng dụng AI', 'is_active' => true]
        );

        $seCurriculum = \App\Models\Curriculum::query()->updateOrCreate(
            ['program_id' => $itProgram->id, 'code' => 'CTDT-KTPM-2024'],
            ['major_id' => $seMajor->id, 'specialization_id' => $webSpec->id, 'name' => 'Chương trình KTPM 2024', 'effective_from' => '2024-08-01', 'is_active' => true]
        );
        $dsCurriculum = \App\Models\Curriculum::query()->updateOrCreate(
            ['program_id' => $itProgram->id, 'code' => 'CTDT-KHDT-2024'],
            ['major_id' => $dsMajor->id, 'specialization_id' => $aiSpec->id, 'name' => 'Chương trình KHDT 2024', 'effective_from' => '2024-08-01', 'is_active' => true]
        );

        $k18Se = Cohort::query()->updateOrCreate(
            ['program_id' => $itProgram->id, 'code' => 'D22KTPM-A'],
            ['institution_id' => $institutionId, 'major_id' => $seMajor->id, 'name' => 'D22 Kỹ thuật phần mềm A', 'start_year' => 2022, 'end_year' => 2026, 'status' => 'active']
        );
        $k18Ds = Cohort::query()->updateOrCreate(
            ['program_id' => $itProgram->id, 'code' => 'D22KHDT-A'],
            ['institution_id' => $institutionId, 'major_id' => $dsMajor->id, 'name' => 'D22 Khoa học dữ liệu A', 'start_year' => 2022, 'end_year' => 2026, 'status' => 'active']
        );

        return compact(
            'formal',
            'partTime',
            'enterprise',
            'itProgram',
            'baProgram',
            'seMajor',
            'dsMajor',
            'webSpec',
            'aiSpec',
            'seCurriculum',
            'dsCurriculum',
            'k18Se',
            'k18Ds'
        );
    }

    private function seedAcademicCalendar(int $institutionId): array
    {
        $year = AcademicYear::query()->updateOrCreate(
            ['institution_id' => $institutionId, 'name' => '2025-2026'],
            ['start_date' => '2025-08-01', 'end_date' => '2026-07-31', 'is_current' => true, 'status' => 'active']
        );

        $term1 = Term::query()->updateOrCreate(
            ['academic_year_id' => $year->id, 'code' => 'HK1'],
            [
                'name' => 'Học kỳ 1',
                'start_date' => '2025-09-01',
                'end_date' => '2026-01-10',
                'enrollment_start_at' => '2025-08-15 00:00:00',
                'enrollment_end_at' => '2025-09-05 23:59:59',
                'exam_start_at' => '2025-12-15 00:00:00',
                'exam_end_at' => '2026-01-10 23:59:59',
                'is_current' => true,
                'status' => 'active',
            ]
        );
        $term2 = Term::query()->updateOrCreate(
            ['academic_year_id' => $year->id, 'code' => 'HK2'],
            [
                'name' => 'Học kỳ 2',
                'start_date' => '2026-02-01',
                'end_date' => '2026-06-15',
                'is_current' => false,
                'status' => 'planned',
            ]
        );

        return compact('year', 'term1', 'term2');
    }

    private function seedUserProfilesAndAssignments(
        int $institutionId,
        array $units,
        array $positions,
        array $programContext,
        array $terms
    ): void {
        $admin = User::query()->where('email', 'admin@lms.com')->first();
        if ($admin) {
            $admin->update([
                'institution_id' => $institutionId,
                'unit_id' => $units['board']->id,
                'user_type' => 'admin',
                'staff_code' => 'ADM001',
                'phone' => '0900000001',
            ]);
            $this->upsertAssignment($admin->id, $units['board']->id, $positions['admin']->id, true);
        }

        $instructors = User::query()->where('email', 'like', 'instructor%@lms.com')->orderBy('id')->get();
        $instructors->each(function (User $user, int $index) use ($institutionId, $units, $positions) {
            $unit = $index % 2 === 0 ? $units['seDept'] : $units['dsDept'];
            $staffCode = 'GV' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
            $user->update([
                'institution_id' => $institutionId,
                'unit_id' => $unit->id,
                'user_type' => 'instructor',
                'staff_code' => $staffCode,
                'phone' => '0910000' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
            ]);
            $this->upsertAssignment($user->id, $unit->id, $positions['lecturer']->id, true);
            // First half are also advisors (cố vấn học tập).
            if ($index < (int) ceil($user->newQuery()->where('email', 'like', 'instructor%@lms.com')->count() / 2)) {
                $user->assignRole('advisor');
            }
        });

        $advisorPool = $instructors->take((int) ceil($instructors->count() / 2))->values();

        User::query()->where('email', 'like', 'student%@lms.com')->get()->each(function (User $user, int $index) use ($institutionId, $units, $positions, $programContext, $advisorPool) {
            $isSe = $index % 2 === 0;
            $major = $isSe ? $programContext['seMajor'] : $programContext['dsMajor'];
            $specialization = $isSe ? $programContext['webSpec'] : $programContext['aiSpec'];
            $cohort = $isSe ? $programContext['k18Se'] : $programContext['k18Ds'];
            $studentCode = 'B22DCCN' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
            $advisor = $advisorPool->isEmpty() ? null : $advisorPool[$index % $advisorPool->count()];

            $user->update([
                'institution_id' => $institutionId,
                'unit_id' => $major->unit_id ?: $units['itFaculty']->id,
                'program_id' => $programContext['itProgram']->id,
                'major_id' => $major->id,
                'specialization_id' => $specialization->id,
                'cohort_id' => $cohort->id,
                'advisor_id' => $advisor?->id,
                'user_type' => 'student',
                'student_code' => $studentCode,
                'phone' => '0980000' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
            ]);
            $this->upsertAssignment($user->id, $major->unit_id ?: $units['itFaculty']->id, $positions['student']->id, true);
        });

        // align seeded enrollments to current term/cohort
        \App\Models\Enrollment::query()
            ->with('user')
            ->get()
            ->each(function (\App\Models\Enrollment $enrollment) use ($terms) {
                if (!$enrollment->user) {
                    return;
                }

                $enrollment->update([
                    'term_id' => $terms['term1']->id,
                    'cohort_id' => $enrollment->user->cohort_id,
                    'enrollment_source' => $enrollment->order_id ? 'marketplace' : 'academic',
                ]);
            });
    }

    private function upsertAssignment(int $userId, int $unitId, int $positionId, bool $isPrimary): void
    {
        UserAssignment::query()->updateOrCreate(
            ['user_id' => $userId, 'unit_id' => $unitId, 'position_id' => $positionId],
            ['is_primary' => $isPrimary, 'status' => 'active', 'start_date' => now()->toDateString(), 'end_date' => null]
        );
    }
}
