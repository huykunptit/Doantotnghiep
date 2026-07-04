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
 * Chạy độc lập: php artisan db:seed --class=UserSeeder
 */
class UserSeeder extends Seeder
{
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
                'name'               => 'Admin LMS',
                'password'           => Hash::make('password'),
                'email_verified_at'  => now(),
                'avatar'             => 'https://i.pravatar.cc/300?img=68',
            ]
        );
        $admin->syncRoles(['admin']);
    }

    private function seedInstructors(): void
    {
        collect(range(1, 8))->each(function (int $i) {
            $user = User::query()->updateOrCreate(
                ['email' => "instructor{$i}@lms.com"],
                [
                    'name'              => "Giảng viên {$i}",
                    'password'          => Hash::make('password'),
                    'email_verified_at' => now(),
                    'avatar'            => 'https://i.pravatar.cc/300?img=' . (10 + $i),
                    'bio'               => "Giảng viên demo số {$i} phụ trách khóa học mẫu cho hệ thống.",
                ]
            );
            $user->syncRoles(['instructor']);
        });
    }

    private function seedStudents(): void
    {
        collect(range(1, 18))->each(function (int $i) {
            $user = User::query()->updateOrCreate(
                ['email' => "student{$i}@lms.com"],
                [
                    'name'              => "Sinh viên {$i}",
                    'password'          => Hash::make('password'),
                    'email_verified_at' => now(),
                    'avatar'            => 'https://i.pravatar.cc/300?img=' . (40 + $i),
                    'bio'               => "Sinh viên demo số {$i} dùng để hiển thị dữ liệu mẫu.",
                ]
            );
            $user->syncRoles(['student']);
        });
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

