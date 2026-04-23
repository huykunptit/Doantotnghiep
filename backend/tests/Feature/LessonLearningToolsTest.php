<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LessonLearningToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_save_progress_and_manage_notes(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $instructor = User::factory()->create();
        $student = User::factory()->create();
        $student->assignRole('student');

        $course = Course::create([
            'user_id' => $instructor->id,
            'title' => 'PHP Basics',
            'slug' => 'php-basics',
            'price' => 0,
            'status' => 'published',
        ]);

        $lesson = Lesson::create([
            'course_id' => $course->id,
            'title' => 'Lesson 1',
            'type' => 'video',
            'order' => 1,
            'duration' => 600,
        ]);

        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id]);

        $this->actingAs($student, 'sanctum')
            ->putJson("/api/courses/{$course->id}/lessons/{$lesson->id}/progress", [
                'progress_percent' => 45,
                'last_position' => 270,
                'watched_seconds' => 320,
            ])
            ->assertOk()
            ->assertJsonPath('progress.progress_percent', 45)
            ->assertJsonPath('progress.last_position', 270);

        $noteResponse = $this->actingAs($student, 'sanctum')
            ->postJson("/api/courses/{$course->id}/lessons/{$lesson->id}/notes", [
                'content' => 'Important note',
                'position_seconds' => 180,
            ])
            ->assertCreated()
            ->assertJsonPath('note.content', 'Important note');

        $noteId = $noteResponse->json('note.id');

        $this->actingAs($student, 'sanctum')
            ->getJson("/api/courses/{$course->id}/lessons/{$lesson->id}/notes")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.position_seconds', 180);

        $this->actingAs($student, 'sanctum')
            ->putJson("/api/courses/{$course->id}/lessons/{$lesson->id}/notes/{$noteId}", [
                'content' => 'Updated note',
            ])
            ->assertOk()
            ->assertJsonPath('note.content', 'Updated note');

        $this->actingAs($student, 'sanctum')
            ->deleteJson("/api/courses/{$course->id}/lessons/{$lesson->id}/notes/{$noteId}")
            ->assertOk();

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'progress_percent' => 45,
        ]);

        $this->assertDatabaseMissing('lesson_notes', ['id' => $noteId]);
    }

    public function test_unenrolled_user_cannot_access_progress_or_notes(): void
    {
        $instructor = User::factory()->create();
        $student = User::factory()->create();
        $course = Course::create([
            'user_id' => $instructor->id,
            'title' => 'Laravel Basics',
            'slug' => 'laravel-basics',
            'price' => 0,
            'status' => 'published',
        ]);
        $lesson = Lesson::create(['course_id' => $course->id, 'title' => 'Lesson', 'type' => 'video', 'order' => 1]);

        $this->actingAs($student, 'sanctum')
            ->getJson("/api/courses/{$course->id}/lessons/{$lesson->id}/progress")
            ->assertForbidden();

        $this->actingAs($student, 'sanctum')
            ->postJson("/api/courses/{$course->id}/lessons/{$lesson->id}/notes", ['content' => 'Blocked'])
            ->assertForbidden();
    }
}

