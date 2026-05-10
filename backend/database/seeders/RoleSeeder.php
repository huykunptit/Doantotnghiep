<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $guard = 'web';

        $permissions = [
            'view_dashboard',
            'manage_users',
            'manage_roles',
            'manage_courses',
            'manage_lessons',
            'manage_exams',
            'manage_finances',
            'manage_academic',
            'manage_enrollments',
            'manage_grades',
            'view_grades',
            'advise_students',
            'take_exams',
            'view_reports',
            'submit_reviews',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => $guard]);
        }

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => $guard]);
        $academicAffairs = Role::firstOrCreate(['name' => 'academic_affairs', 'guard_name' => $guard]);
        $instructor = Role::firstOrCreate(['name' => 'instructor', 'guard_name' => $guard]);
        $advisor = Role::firstOrCreate(['name' => 'advisor', 'guard_name' => $guard]);
        $finance = Role::firstOrCreate(['name' => 'finance', 'guard_name' => $guard]);
        $student = Role::firstOrCreate(['name' => 'student', 'guard_name' => $guard]);

        $admin->syncPermissions(Permission::query()->where('guard_name', $guard)->get());

        $academicAffairs->syncPermissions([
            'view_dashboard',
            'manage_academic',
            'manage_enrollments',
            'manage_grades',
            'view_grades',
            'view_reports',
        ]);

        $instructor->syncPermissions([
            'view_dashboard',
            'manage_courses',
            'manage_lessons',
            'manage_exams',
            'manage_grades',
            'view_grades',
            'view_reports',
        ]);

        $advisor->syncPermissions([
            'view_dashboard',
            'advise_students',
            'view_grades',
            'view_reports',
        ]);

        $finance->syncPermissions([
            'view_dashboard',
            'manage_finances',
            'view_reports',
        ]);

        $student->syncPermissions([
            'view_grades',
            'take_exams',
            'submit_reviews',
        ]);
    }
}
