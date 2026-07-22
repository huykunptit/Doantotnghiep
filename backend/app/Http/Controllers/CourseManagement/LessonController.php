<?php

namespace App\Http\Controllers\CourseManagement;

use App\Http\Controllers\Controller;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LessonController extends Controller
{
    protected $mediaService;

    public function __construct(MediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    public function index(Request $request, Course $course): JsonResponse
    {
        // Public route: explicitly resolve via sanctum so a Bearer token still
        // identifies the user (admin/owner/enrolled) without forcing auth.
        /** @var \App\Models\User|null $user */
        $user = $request->user() ?? auth('sanctum')->user();

        $isOwner = $user && ($user->hasRole('admin') || $course->user_id === $user->id);
        $isEnrolled = $user && Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)->exists();

        $lessons = $course->lessons()->orderBy('order');

        if ($request->boolean('include_attachments')) {
            $lessons->with('attachments');
        }

        $lessons = $lessons->get();

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
            'type'       => ['nullable', 'string', 'in:video,audio,file,page,quiz,assignment,forum,survey,zoom,meet,document,virtual_class,scorm,h5p'],
            'is_preview' => ['nullable', 'boolean'],
            'order'      => ['nullable', 'integer', 'min:0'],
            'duration'   => ['nullable', 'integer', 'min:0'],
            'video_url'  => ['nullable', 'string', 'max:2048'],
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
        $user = $request->user() ?? auth('sanctum')->user();

        $isOwner = $user && ($user->hasRole('admin') || $course->user_id === $user->id);
        $isEnrolled = $user && Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)->exists();

        if (!$lesson->is_preview && !$isOwner && !$isEnrolled) {
            return response()->json(['message' => 'Enrollment required'], 403);
        }

        // Load content-specific relations
        $lesson->load(['virtualClass', 'scormPackage', 'offlineSession', 'assignment', 'attachments', 'quiz']);

        if ($lesson->video_url && !filter_var($lesson->video_url, FILTER_VALIDATE_URL)) {
            $lesson->video_url = $this->mediaService->getUrl($lesson->video_url);
        }

        $lesson->attachments->transform(function ($attachment) {
            $attachment->url = $this->mediaService->getUrl($attachment->file_path);
            return $attachment;
        });

        // Resolve SCORM entry_url: MinIO keys → /minio/<bucket>/<key>, relative local → full URL.
        if ($lesson->scormPackage && $lesson->scormPackage->entry_url) {
            $url = $lesson->scormPackage->entry_url;
            if (!str_starts_with($url, 'http') && !str_starts_with($url, '/minio/')) {
                // Legacy local path — resolve via public storage URL
                if (str_starts_with($url, 'scorm/')) {
                    $bucket = config('filesystems.disks.minio.bucket', 'lms-videos');
                    $lesson->scormPackage->entry_url = '/minio/' . $bucket . '/' . $url;
                } else {
                    $lesson->scormPackage->entry_url = \Illuminate\Support\Facades\Storage::disk('public')->url($url);
                }
            }
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
            'type'        => ['sometimes', 'string', 'in:video,audio,file,page,quiz,assignment,forum,survey,zoom,meet,document,virtual_class,scorm,h5p'],
            'is_preview'  => ['nullable', 'boolean'],
            'order'       => ['sometimes', 'integer', 'min:0'],
            'duration'    => ['sometimes', 'integer', 'min:0'],
            'video_url'   => ['nullable', 'string', 'max:2048'],
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

        $contentLength = (int) $request->server('CONTENT_LENGTH', 0);
        $postMax = ini_get('post_max_size');
        $uploadMax = ini_get('upload_max_filesize');

        // Detect PHP silently truncating the body (post_max_size exceeded).
        if ($contentLength > 0 && empty($_FILES) && empty($_POST) && !$request->hasFile('video')) {
            return response()->json([
                'message' => "PHP cắt body ({$contentLength} bytes) vì vượt post_max_size={$postMax}. Khởi động lại backend với -d post_max_size=1024M -d upload_max_filesize=1024M.",
                'errors'  => ['video' => ['File vượt post_max_size hiện tại.']],
                'debug'   => compact('contentLength', 'postMax', 'uploadMax'),
            ], 413);
        }

        // Detect upload_max_filesize exceeded → file present in $_FILES but with error code.
        $rawError = $_FILES['video']['error'] ?? null;
        if ($rawError !== null && $rawError !== UPLOAD_ERR_OK) {
            $errMap = [
                UPLOAD_ERR_INI_SIZE   => "File vượt upload_max_filesize={$uploadMax} của PHP. Restart backend với -d upload_max_filesize=1024M.",
                UPLOAD_ERR_FORM_SIZE  => 'File vượt MAX_FILE_SIZE chỉ định ở form.',
                UPLOAD_ERR_PARTIAL    => 'File chỉ upload được một phần.',
                UPLOAD_ERR_NO_FILE    => 'Không có file nào được upload.',
                UPLOAD_ERR_NO_TMP_DIR => 'Thiếu thư mục tạm.',
                UPLOAD_ERR_CANT_WRITE => 'Không ghi được file lên disk.',
                UPLOAD_ERR_EXTENSION  => 'Một extension PHP đã chặn upload.',
            ];
            $msg = $errMap[$rawError] ?? "PHP upload error code {$rawError}.";
            return response()->json([
                'message' => $msg,
                'errors'  => ['video' => [$msg]],
                'debug'   => [
                    'php_upload_err' => $rawError,
                    'contentLength'  => $contentLength,
                    'postMax'        => $postMax,
                    'uploadMax'      => $uploadMax,
                ],
            ], 413);
        }

        $request->validate([
            'video' => ['required', 'file', 'max:1024000'], // 1GB max — extension check below
        ]);

        $extension = strtolower($request->file('video')->getClientOriginalExtension());
        if (!in_array($extension, ['mp4', 'mov', 'avi', 'webm', 'mkv', 'm4v'])) {
            return response()->json([
                'message' => "Định dạng .{$extension} không được hỗ trợ. Chỉ chấp nhận: mp4, mov, avi, webm, mkv, m4v.",
                'errors'  => ['video' => ["Định dạng .{$extension} không hợp lệ."]],
            ], 422);
        }

        try {
            $file = $request->file('video');
            $uploadDate = date('Y-m-d');
            $targetFolder = $uploadDate;
            $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();

            $path = Storage::disk('public_uploads')->putFileAs(
                $targetFolder,
                $file,
                $filename,
                'public'
            );

            if (!$path) {
                throw new \Exception('Failed to upload video to public/uploads.');
            }

            // Delete old video if it was stored locally under public_uploads
            if ($lesson->video_url) {
                $oldPath = ltrim($lesson->video_url, '/');
                if (Storage::disk('public_uploads')->exists($oldPath)) {
                    Storage::disk('public_uploads')->delete($oldPath);
                }
            }

            $lesson->update([
                'video_url' => $path,
                'video_size' => round($file->getSize() / 1024 / 1024, 2) . 'MB',
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
        $user = $request->user() ?? auth('sanctum')->user();

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
