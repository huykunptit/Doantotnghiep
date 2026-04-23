<?php

namespace App\Http\Controllers\CourseManagement;

use App\Http\Controllers\Controller;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonAttachment;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonAttachmentController extends Controller
{
    protected $mediaService;

    public function __construct(MediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    /**
     * List attachments for a lesson.
     */
    public function index(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        if ($lesson->course_id !== $course->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $attachments = $lesson->attachments->map(function ($attachment) {
            $attachment->url = $this->mediaService->getUrl($attachment->file_path);
            return $attachment;
        });

        return response()->json(['attachments' => $attachments]);
    }

    /**
     * Upload an attachment.
     */
    public function store(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (!$user->hasRole('admin') && $course->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'file' => 'required|file|max:51200', // 50MB
        ]);

        $file = $request->file('file');
        
        $uploadResult = $this->mediaService->upload(
            $file, 
            "courses/{$course->id}/lessons/{$lesson->id}/attachments"
        );

        $attachment = $lesson->attachments()->create([
            'original_name' => $uploadResult['name'],
            'file_path' => $uploadResult['path'],
            'file_size' => $uploadResult['size'] . 'MB',
            'mime_type' => $uploadResult['mime'],
        ]);

        return response()->json([
            'message' => 'Attachment uploaded successfully',
            'attachment' => $attachment,
        ], 201);
    }

    /**
     * Delete an attachment.
     */
    public function destroy(Request $request, Course $course, Lesson $lesson, LessonAttachment $attachment): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (!$user->hasRole('admin') && $course->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($attachment->lesson_id !== $lesson->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $this->mediaService->delete($attachment->file_path);
        $attachment->delete();

        return response()->json(['message' => 'Attachment deleted']);
    }

    /**
     * Download an attachment (Generates accessible URL).
     */
    public function download(Request $request, Course $course, Lesson $lesson, LessonAttachment $attachment): JsonResponse
    {
        if ($attachment->lesson_id !== $lesson->id) {
            return response()->json(['message' => 'Not found'], 404);
        }
        
        $url = $this->mediaService->getUrl($attachment->file_path);

        return response()->json(['url' => $url]);
    }
}
