<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Hash;

/**
 * Seed tài khoản người dùng:
 *  - 1 admin
 *  - 8 instructor (instructor1@lms.com … instructor8@lms.com)
 *  - student1@lms.com … student16@lms.com  (tài khoản demo gốc, giữ nguyên email)
 *  - student17@… trở đi: tạm @stu.ptit.edu.vn → OrgAcademic đổi thành {mã SV}@stu.ptit.edu.vn
 *
 * Chạy độc lập: php artisan db:seed --class=UserSeeder
 */
class UserSeeder extends Seeder
{
    /** @var list<array{name: string, bio: string}> */
    private const INSTRUCTORS = [
        [
            'name' => 'TS. Nguyễn Văn Hùng',
            'bio' => 'Tiến sĩ Khoa học máy tính, phụ trách lập trình web và kiến trúc phần mềm.',
        ],
        [
            'name' => 'ThS. Trần Thị Mai',
            'bio' => 'Thạc sĩ Hệ thống thông tin, chuyên môn cơ sở dữ liệu và phân tích dữ liệu.',
        ],
        [
            'name' => 'TS. Lê Minh Tuấn',
            'bio' => 'Tiến sĩ Mạng máy tính, giảng dạy mạng máy tính, bảo mật và DevOps.',
        ],
        [
            'name' => 'ThS. Phạm Thu Hà',
            'bio' => 'Thạc sĩ Công nghệ phần mềm, phụ trách UI/UX, Vue/Nuxt và trải nghiệm học tập số.',
        ],
        [
            'name' => 'PGS.TS. Hoàng Đức Anh',
            'bio' => 'Phó Giáo sư ngành Điện tử – Viễn thông, hướng dẫn IoT và hệ thống nhúng.',
        ],
        [
            'name' => 'ThS. Vũ Quỳnh Anh',
            'bio' => 'Thạc sĩ Quản trị kinh doanh, giảng dạy BA, quản lý dự án và chuyển đổi số.',
        ],
        [
            'name' => 'TS. Đặng Quốc Bảo',
            'bio' => 'Tiến sĩ Trí tuệ nhân tạo, phụ trách Python, machine learning và tư vấn nghề IT.',
        ],
        [
            'name' => 'ThS. Bùi Thanh Sơn',
            'bio' => 'Thạc sĩ Kỹ thuật phần mềm, phụ trách mobile Flutter và kiểm thử phần mềm.',
        ],
    ];

    /**
     * Danh sách SV gốc (18) + bổ sung thêm để đủ phân bổ ~10 SV/lớp hành chính (demo khảo sát).
     * Email tạm: student{n}@stu.ptit.edu.vn → OrgAcademicSeeder đổi sang {mã SV}@stu.ptit.edu.vn.
     *
     * @var list<string>
     */
    private const STUDENTS = [
        // —— 18 tài khoản gốc ——
        'Nguyễn Minh Anh',
        'Trần Hoàng Long',
        'Lê Thị Thu Trang',
        'Phạm Đức Thịnh',
        'Hoàng Ngọc Bảo',
        'Vũ Phương Linh',
        'Đặng Hải Nam',
        'Bùi Khánh Vy',
        'Đỗ Quang Huy',
        'Ngô Thị Hương',
        'Lý Văn Khoa',
        'Mai Thanh Tùng',
        'Phan Thị Lan Anh',
        'Chu Minh Đức',
        'Tạ Thị Mỹ Dung',
        'Lương Nhật Minh',
        'Hồ Gia Bảo',
        'Đinh Thị Quỳnh Nga',
        // —— Bổ sung SV chuẩn cùng form ——
        'Nguyễn Thị Mai Chi',
        'Trần Văn Đức',
        'Lê Hoàng Nam',
        'Phạm Thị Hồng Nhung',
        'Hoàng Minh Tuấn',
        'Vũ Thị Thanh Hà',
        'Đặng Quốc Huy',
        'Bùi Thị Ngọc Anh',
        'Đỗ Văn Phúc',
        'Ngô Thị Kim Oanh',
        'Lý Minh Châu',
        'Mai Thị Thuỳ Dung',
        'Phan Văn Hưng',
        'Chu Thị Lan',
        'Tạ Minh Khôi',
        'Lương Thị Bảo Trân',
        'Hồ Văn Thành',
        'Đinh Thị Thuý',
        'Nguyễn Văn An',
        'Trần Thị Bình',
        'Lê Minh Cường',
        'Phạm Thị Duyên',
        'Hoàng Văn Em',
        'Vũ Thị Phương',
        'Đặng Minh Giang',
        'Bùi Thị Hạnh',
        'Đỗ Văn Ích',
        'Ngô Thị Kim',
        'Lý Văn Lâm',
        'Mai Thị My',
        'Phan Văn Ninh',
        'Chu Thị Oanh',
        'Tạ Văn Phát',
        'Lương Thị Quyên',
        'Hồ Văn Sơn',
        'Đinh Thị Trinh',
        'Nguyễn Hoàng Uyên',
        'Trần Minh Vũ',
        'Lê Thị Xuân',
        'Phạm Văn Yên',
        'Hoàng Thị Ánh',
        'Vũ Minh Bảo',
        'Đặng Thị Cẩm',
        'Bùi Văn Đạt',
        'Đỗ Thị Hằng',
        'Ngô Minh Kiên',
        'Lý Thị Loan',
        'Mai Văn Nhật',
        'Phan Thị Phương Anh',
        'Chu Văn Quang',
        'Tạ Thị Sen',
        'Lương Văn Thái',
        'Hồ Thị Uyển',
        'Đinh Văn Vinh',
        'Nguyễn Thị Yến Nhi',
        'Trần Quốc Bảo',
        'Lê Thị Diệu',
        'Phạm Minh Đức',
        'Hoàng Thị Hảo',
        'Vũ Văn Khải',
        'Đặng Thị Linh Chi',
        'Bùi Minh Nhật',
        'Đỗ Thị Phương Thảo',
        'Ngô Văn Quý',
        'Lý Thị Sương',
        'Mai Hoàng Tâm',
        'Phan Thị Út',
        'Chu Minh Vương',
        'Tạ Thị Xuân Mai',
        'Lương Minh Ý',
        'Hồ Thị Ái',
        'Đinh Minh Bách',
        'Nguyễn Thị Cúc',
        'Trần Văn Duy',
        'Lê Thị Hạnh Nguyên',
        'Phạm Hoàng Khoa',
        'Hoàng Thị Lệ',
        'Vũ Văn Mạnh',
        'Đặng Thị Nga',
        'Bùi Hoàng Phú',
        'Đỗ Thị Quế',
        'Ngô Văn Rạng',
        'Lý Thị Sáng',
        'Mai Văn Tài',
        'Phan Thị Vân',
        'Chu Hoàng Việt',
        'Tạ Thị Xuân',
        'Lương Văn Yên',
        'Hồ Thị Anh Thư',
        'Đinh Hoàng Bình',
        'Nguyễn Văn Cảnh',
        'Trần Thị Đào',
        'Lê Hoàng Phong',
        'Phạm Thị Giang',
        'Hoàng Văn Hải',
        'Vũ Thị Khánh',
        'Đặng Hoàng Long',
        'Bùi Thị Minh',
        'Đỗ Hoàng Nam',
        'Ngô Thị Phương',
        'Lý Hoàng Quân',
        'Mai Thị Thuý Hằng',
    ];

    public function run(): void
    {
        $this->seedAdmin();
        $this->seedInstructors();
        $this->seedStudents();
    }

    private function seedAdmin(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@lms.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'avatar' => 'https://i.pravatar.cc/300?img=68',
            ]
        );
        $admin->syncRoles(['admin']);
    }

    private function seedInstructors(): void
    {
        foreach (self::INSTRUCTORS as $i => $profile) {
            $n = $i + 1;
            $user = User::query()->updateOrCreate(
                ['email' => "instructor{$n}@lms.com"],
                [
                    'name' => $profile['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'avatar' => 'https://i.pravatar.cc/300?img=' . (10 + $n),
                    'bio' => $profile['bio'],
                ]
            );
            $user->syncRoles(['instructor']);
        }
    }

    public const DEMO_LMS_STUDENT_COUNT = 16;

    private function seedStudents(): void
    {
        foreach (self::STUDENTS as $i => $fullName) {
            $n = $i + 1;
            $isDemoLms = $n <= self::DEMO_LMS_STUDENT_COUNT;
            $email = $isDemoLms
                ? "student{$n}@lms.com"
                : "student{$n}@stu.ptit.edu.vn";

            $user = User::query()
                ->whereIn('email', [
                    $email,
                    "student{$n}@lms.com",
                    "student{$n}@stu.ptit.edu.vn",
                ])
                ->first()
                ?? User::query()
                    ->where('name', $fullName)
                    ->where(function ($q) {
                        $q->where('user_type', 'student')
                            ->orWhere('email', 'like', '%@stu.ptit.edu.vn')
                            ->orWhere('email', 'like', 'student%@lms.com');
                    })
                    ->orderBy('id')
                    ->first();

            $payload = [
                'name' => $fullName,
                'email' => $email,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'avatar' => sprintf(
                    '/storage/seed/avatars/%s-%02d.jpg',
                    $n % 2 === 1 ? 'male' : 'female',
                    (intdiv($n - 1, 2) % 16) + 1,
                ),
                'bio' => 'Sinh viên Học viện Công nghệ Bưu chính Viễn thông, học tập trên Eript LMS.',
            ];

            // SV 17+: nếu đã có mã SV (@stu.ptit.edu.vn dạng mã) thì giữ email mã, chỉ cập nhật profile
            if ($user && !$isDemoLms && preg_match('/^[a-z0-9]+@stu\.ptit\.edu\.vn$/i', (string) $user->email)
                && !str_starts_with(strtolower((string) $user->email), 'student')) {
                unset($payload['email']);
            }

            if ($user) {
                $user->fill($payload)->save();
            } else {
                $user = User::query()->create($payload);
            }

            $user->syncRoles(['student']);
        }

        $this->command?->info('UserSeeder: ' . count(self::STUDENTS) . ' students upserted (student1–16@lms.com demo).');
    }

    // ── Helpers cho các seeder khác cần collection ───────────────────────────

    public static function getAdmin(): User
    {
        return User::query()->where('email', 'admin@lms.com')->firstOrFail();
    }

    public static function getInstructors(): EloquentCollection
    {
        return User::query()
            ->where('email', 'like', 'instructor%@lms.com')
            ->orderBy('id')
            ->get();
    }

    public static function getStudents(): EloquentCollection
    {
        return User::role('student')
            ->orderBy('id')
            ->get();
    }

    /** @return list<string> */
    public static function studentNames(): array
    {
        return self::STUDENTS;
    }
}
