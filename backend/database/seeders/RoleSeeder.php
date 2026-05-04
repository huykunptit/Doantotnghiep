<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Permissions
        $permissions = [
            'view_dashboard',
            'manage_users',
            'manage_roles',
            'manage_courses',
            'manage_lessons',
            'manage_exams',
            'manage_finances',
            'take_exams',
            'view_reports',
            'submit_reviews',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // 2. Create Roles
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $instructor = Role::firstOrCreate(['name' => 'instructor']);
        $student = Role::firstOrCreate(['name' => 'student']);

        // 3. Assign default permissions
        $admin->syncPermissions(Permission::all());
        
        $instructor->syncPermissions([
            'view_dashboard',
            'manage_courses',
            'manage_lessons',
            'manage_exams',
            'view_reports'
        ]);

        $student->syncPermissions([
            'take_exams',
            'submit_reviews'
        ]);
    }
}
