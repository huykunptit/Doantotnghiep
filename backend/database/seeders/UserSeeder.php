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
 *  - 18 student  (student1@lms.com  … student18@lms.com)
 *
 * Email giữ dạng demo để các seeder khác tra cứu ổn định; tên là họ-tên Việt.
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

    /** @var list<string> */
    private const STUDENTS = [
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
                'name' => 'Nguyễn Thanh Bình',
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

    private function seedStudents(): void
    {
        foreach (self::STUDENTS as $i => $fullName) {
            $n = $i + 1;
            $user = User::query()->updateOrCreate(
                ['email' => "student{$n}@lms.com"],
                [
                    'name' => $fullName,
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'avatar' => 'https://i.pravatar.cc/300?img=' . (40 + $n),
                    'bio' => "Sinh viên ngành đào tạo tại Học viện, tham gia học tập trên Eript LMS.",
                ]
            );
            $user->syncRoles(['student']);
        }
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
        return User::query()
            ->where('email', 'like', 'student%@lms.com')
            ->orderBy('id')
            ->get();
    }
}
