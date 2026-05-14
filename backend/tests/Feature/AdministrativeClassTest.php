<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\Major;
use App\Models\Position;
use App\Models\Program;
use App\Models\ProgramType;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdministrativeClassTest extends TestCase
{
    use RefreshDatabase;

    public function test_academic_manager_can_crud_administrative_classes(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $manager = User::factory()->create();
        $manager->assignRole('academic_manager');
        $institution = Institution::create(['name' => 'PTIT', 'code' => 'PTIT']);
        $unit = Unit::create(['institution_id' => $institution->id, 'name' => 'CNTT1', 'code' => 'CNTT1', 'unit_type' => 'faculty']);
        $programType = ProgramType::create(['name' => 'Chính quy', 'code' => 'CQ']);
        $program = Program::create(['institution_id' => $institution->id, 'unit_id' => $unit->id, 'program_type_id' => $programType->id, 'name' => 'CNTT', 'code' => 'CNTT']);
        $position = Position::create([
            'name' => 'Quản lý học tập',
            'code' => 'ACADEMIC_MANAGER',
            'scope_level' => 'unit',
            'is_active' => true,
        ]);

        $major = Major::create(['program_id' => $program->id, 'unit_id' => $unit->id, 'name' => 'KTPM', 'code' => 'KTPM']);
        $cohort = \App\Models\Cohort::create(['institution_id' => $institution->id, 'program_id' => $program->id, 'major_id' => $major->id, 'name' => 'D22 CNTT', 'code' => 'D22CNTT', 'start_year' => 2022]);
        UserAssignment::create([
            'user_id' => $manager->id,
            'unit_id' => $unit->id,
            'position_id' => $position->id,
            'is_primary' => true,
            'status' => 'active',
        ]);


        $payload = [
            'institution_id' => $institution->id,
            'unit_id' => $unit->id,
            'program_id' => $program->id,
            'major_id' => $major->id,
            'cohort_id' => $cohort->id,
            'code' => 'D22CNTT1',
            'name' => 'Lớp D22 CNTT 1',
            'capacity' => 40,
            'status' => 'active',
        ];

        $response = $this->actingAs($manager, 'sanctum')
            ->postJson('/api/admin/academic/administrative-classes', $payload)
            ->assertCreated();

        $id = $response->json('id');

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/admin/academic/administrative-classes')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'D22CNTT1');

        $this->actingAs($manager, 'sanctum')
            ->putJson("/api/admin/academic/administrative-classes/{$id}", ['name' => 'Lớp D22 CNTT 01'])
            ->assertOk()
            ->assertJsonPath('name', 'Lớp D22 CNTT 01');

        $this->actingAs($manager, 'sanctum')
            ->deleteJson("/api/admin/academic/administrative-classes/{$id}")
            ->assertOk();

        $this->assertDatabaseMissing('administrative_classes', ['id' => $id]);
    }

    public function test_instructor_cannot_manage_administrative_classes(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $instructor = User::factory()->create();
        $instructor->assignRole('instructor');

        $this->actingAs($instructor, 'sanctum')
            ->getJson('/api/admin/academic/administrative-classes')
            ->assertForbidden();
    }
}

