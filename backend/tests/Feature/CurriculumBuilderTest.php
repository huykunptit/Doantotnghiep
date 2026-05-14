<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Curriculum;
use App\Models\Institution;
use App\Models\Major;
use App\Models\Program;
use App\Models\ProgramType;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurriculumBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_academic_manager_can_bulk_upsert_and_group_curriculum_courses(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $manager = User::factory()->create();
        $manager->assignRole('academic_manager');

        $institution = Institution::create(['name' => 'PTIT', 'code' => 'PTIT']);
        $unit = Unit::create(['institution_id' => $institution->id, 'name' => 'CNTT1', 'code' => 'CNTT1', 'unit_type' => 'faculty']);
        $programType = ProgramType::create(['name' => 'Chính quy', 'code' => 'CQ']);
        $program = Program::create(['institution_id' => $institution->id, 'unit_id' => $unit->id, 'program_type_id' => $programType->id, 'name' => 'CNTT', 'code' => 'CNTT']);
        $major = Major::create(['program_id' => $program->id, 'unit_id' => $unit->id, 'name' => 'KTPM', 'code' => 'KTPM']);
        $curriculum = Curriculum::create(['program_id' => $program->id, 'major_id' => $major->id, 'name' => 'CTĐT CNTT', 'code' => 'CTDT-CNTT']);

        $course1 = Course::create(['user_id' => $manager->id, 'title' => 'Nhập môn', 'slug' => 'nhap-mon', 'price' => 0, 'status' => 'published']);
        $course2 = Course::create(['user_id' => $manager->id, 'title' => 'Cấu trúc dữ liệu', 'slug' => 'ctdl', 'price' => 0, 'status' => 'published']);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/admin/academic/curricula/{$curriculum->id}/courses", [
                ['course_id' => $course1->id, 'term_number' => 1, 'is_required' => true, 'credits' => 3, 'position' => 1],
                ['course_id' => $course2->id, 'term_number' => 2, 'is_required' => true, 'credits' => 4, 'position' => 1],
            ])
            ->assertOk();

        $this->actingAs($manager, 'sanctum')
            ->getJson("/api/admin/academic/curricula/{$curriculum->id}/courses")
            ->assertOk()
            ->assertJsonPath('1.0.course_id', $course1->id)
            ->assertJsonPath('2.0.course_id', $course2->id);

        $this->assertDatabaseHas('curriculum_courses', [
            'curriculum_id' => $curriculum->id,
            'course_id' => $course1->id,
            'term_number' => 1,
        ]);
    }
}

