<?php

namespace App\Http\Controllers;

use App\Jobs\UploadLessonVideoJob;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Services\VideoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LessonController extends Controller
{
    public function index(Request $request, Course $course): JsonResponse
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        $isOwner = $user && ($user->hasRole('admin') || $course->user_id === $user->id);
        $isEnrolled = $user && Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)->exists();

        $lessons = $course->lessons()->orderBy('order')->get();

        // Public visitors: trả đủ meta nhưng ẩn video_url, đánh dấu locked
        if (!$isOwner && !$isEnrolled) {
            $lessons = $lessons->map(function (Lesson $lesson) {
                return [
                    'id'           => $lesson->id,
                    'section_id'   => $lesson->section_id,
                    'title'        => $lesson->title,
                    'description'  => $lesson->description,
                    'order'        => $lesson->order,
                    'duration'     => $lesson->duration,
                    'is_preview'   => $lesson->is_preview,
                    'video_status' => $lesson->video_status,
                    'video_size'   => $lesson->video_size,
                    'locked'       => !$lesson->is_preview, // preview lessons không bị lock
                ];
            });
        }

        return response()->json($lessons);
    }

    public function store(Request $request, Course $course): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (!$user->hasRole('admin') && $course->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'title'      => ['required', 'string', 'max:255'],
            'description'=> ['nullable', 'string'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'is_preview' => ['nullable', 'boolean'],
            'order'      => ['nullable', 'integer', 'min:0'],
            'duration'   => ['nullable', 'integer', 'min:0'],
        ]);

        $maxOrder = $course->lessons()->max('order') ?? 0;

        $lesson = $course->lessons()->create([
            'title'       => $validated['title'],
            'description' => $validated['description'] ?? null,
            'section_id'  => $validated['section_id'] ?? null,
            'is_preview'  => $validated['is_preview'] ?? false,
            'order'       => $validated['order'] ?? ($maxOrder + 1),
            'duration'    => $validated['duration'] ?? 0,
        ]);

        return response()->json([
            'message' => 'Lesson created',
            'lesson'  => $lesson,
        ], 201);
    }

    public function show(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        if ($lesson->course_id !== $course->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        /** @var \App\Models\User|null $user */
        $user = $request->user();

        $isOwner = $user && ($user->hasRole('admin') || $course->user_id === $user->id);
        $isEnrolled = $user && Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)->exists();

        if (!$isOwner && !$isEnrolled && $course->price > 0) {
            return response()->json(['message' => 'Enrollment required'], 403);
        }

        return response()->json($lesson);
    }

    public function update(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        if ($lesson->course_id !== $course->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        /** @var \App\Models\User $user */
        $user = $request->user();

        if (!$user->hasRole('admin') && $course->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'title'       => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'section_id'  => ['nullable', 'integer', 'exists:sections,id'],
            'is_preview'  => ['nullable', 'boolean'],
            'order'       => ['sometimes', 'integer', 'min:0'],
            'duration'    => ['sometimes', 'integer', 'min:0'],
        ]);

        $lesson->fill($validated)->save();

        return response()->json(['message' => 'Lesson updated', 'lesson' => $lesson]);
    }

    public function destroy(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        if ($lesson->course_id !== $course->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        /** @var \App\Models\User $user */
        $user = $request->user();

        if (!$user->hasRole('admin') && $course->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($lesson->video_url) {
            $path = parse_url($lesson->video_url, PHP_URL_PATH);
            $key = ltrim($path, '/lms-videos/');
            Storage::disk('s3')->delete($key);
        }

        $lesson->delete();

        return response()->json(['message' => 'Lesson deleted']);
    }

    public function uploadVideo(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        if ($lesson->course_id !== $course->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        /** @var \App\Models\User $user */
        $user = $request->user();

        if (!$user->hasRole('admin') && $course->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'video' => ['required', 'file', 'mimes:mp4,mov,avi,webm', 'max:512000'], // 500MB max
        ]);

        try {
            $videoService = app(VideoService::class);
            $file = $request->file('video');

            // Upload video to MinIO
            $path = "courses/{$course->id}/lessons/{$lesson->id}";
            $result = $videoService->uploadVideo($file, $path);

            // Delete old video if exists
            if ($lesson->video_url && $videoService->videoExists($lesson->video_url)) {
                $videoService->deleteVideo($lesson->video_url);
            }

            // Update lesson
            $lesson->update([
                'video_url' => $result['path'],
                'video_size' => $result['size'] . 'MB',
                'duration' => $result['duration'],
                'video_status' => 'ready',
            ]);

            return response()->json([
                'message' => 'Video uploaded successfully',
                'lesson' => $lesson->fresh(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Video upload failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function videoUrl(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        if ($lesson->course_id !== $course->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        /** @var \App\Models\User|null $user */
        $user = $request->user();

        $isOwner = $user && ($user->hasRole('admin') || $course->user_id === $user->id);
        $isEnrolled = $user && Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)->exists();

        // Allow preview lessons to be watched by anyone
        if (!$lesson->is_preview && !$isOwner && !$isEnrolled) {
            return response()->json(['message' => 'Enrollment required'], 403);
        }

        if (!$lesson->video_url) {
            return response()->json(['message' => 'No video uploaded yet'], 404);
        }

        try {
            $videoService = app(VideoService::class);

            // Generate presigned URL (15 minutes TTL)
            $presignedUrl = $videoService->generatePresignedUrl($lesson->video_url, 15);
            $expiresAt = now()->addMinutes(15)->toIso8601String();

            return response()->json([
                'url' => $presignedUrl,
                'expires_at' => $expiresAt,
                'expires_in' => 900, // 15 minutes in seconds
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate video URL',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function reorder(Request $request, Course $course): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (!$user->hasRole('admin') && $course->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'order'   => ['required', 'array'],
            'order.*' => ['integer'],
        ]);

        foreach ($validated['order'] as $index => $lessonId) {
            $course->lessons()->where('id', $lessonId)->update(['order' => $index + 1]);
        }

        return response()->json(['message' => 'Lessons reordered']);
    }
}
