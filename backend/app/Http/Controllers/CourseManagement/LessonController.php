<?php

namespace App\Http\Controllers\CourseManagement;

use App\Http\Controllers\Controller;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonController extends Controller
{
    protected $mediaService;

    public function __construct(MediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }

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
                    'type'         => $lesson->type, // Thêm type để frontend biết loại học liệu
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
            'type'       => ['nullable', 'string', 'in:video,document,quiz,assignment,virtual_class,scorm,h5p'],
            'is_preview' => ['nullable', 'boolean'],
            'order'      => ['nullable', 'integer', 'min:0'],
            'duration'   => ['nullable', 'integer', 'min:0'],
            'video_url'  => ['nullable', 'url', 'max:2048'],
        ]);

        $maxOrder = $course->lessons()->max('order') ?? 0;

        $lesson = $course->lessons()->create([
            'title'       => $validated['title'],
            'description' => $validated['description'] ?? null,
            'section_id'  => $validated['section_id'] ?? null,
            'type'        => $validated['type'] ?? 'video',
            'is_preview'  => $validated['is_preview'] ?? false,
            'order'       => $validated['order'] ?? ($maxOrder + 1),
            'duration'    => $validated['duration'] ?? 0,
            'video_url'   => $validated['video_url'] ?? null,
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

        if (!$lesson->is_preview && !$isOwner && !$isEnrolled) {
            return response()->json(['message' => 'Enrollment required'], 403);
        }

        // Load content-specific relations
        $lesson->load(['virtualClass', 'scormPackage', 'offlineSession', 'assignment']);

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
            'type'        => ['sometimes', 'string', 'in:video,document,quiz,assignment,virtual_class,scorm,h5p'],
            'is_preview'  => ['nullable', 'boolean'],
            'order'       => ['sometimes', 'integer', 'min:0'],
            'duration'    => ['sometimes', 'integer', 'min:0'],
            'video_url'   => ['nullable', 'url', 'max:2048'],
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
            $this->mediaService->delete($lesson->video_url);
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
            $file = $request->file('video');

            // Upload using unified MediaService
            $path = "courses/{$course->id}/lessons/{$lesson->id}";
            $result = $this->mediaService->upload($file, $path);

            // Delete old video if exists
            if ($lesson->video_url && $this->mediaService->exists($lesson->video_url)) {
                $this->mediaService->delete($lesson->video_url);
            }

            // Update lesson
            $lesson->update([
                'video_url' => $result['path'],
                'video_size' => $result['size'] . 'MB',
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

        $presignedUrl = filter_var($lesson->video_url, FILTER_VALIDATE_URL)
            ? $lesson->video_url
            : $this->mediaService->getUrl($lesson->video_url);

        return response()->json([
            'url' => $presignedUrl,
            'expires_at' => now()->addHour()->toIso8601String(),
            'expires_in' => 3600, // Service defaults to 60 mins for cloud
        ]);
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
