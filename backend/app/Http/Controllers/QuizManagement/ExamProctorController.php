<?php

namespace App\Http\Controllers\QuizManagement;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamViolation;
use App\Models\Notification;
use App\Models\QuizAttempt;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExamProctorController extends Controller
{
    // ── Admin Actions on Attempts ───────────────────────────────────────

    /**
     * Pause an active attempt (admin/instructor action).
     */
    public function pauseAttempt(Request $request, QuizAttempt $attempt): JsonResponse
    {
        $this->authorizeProctor($request, $attempt);

        if (!$attempt->isActive()) {
            return response()->json([
                'message' => 'Chỉ có thể tạm dừng bài thi đang thực hiện.',
                'current_status' => $attempt->status,
            ], 422);
        }

        $attempt->update([
            'status'    => 'paused',
            'paused_at' => now(),
        ]);

        // Notify the student
        Notification::send(
            $attempt->user_id,
            'exam_paused',
            'Bài thi đã bị tạm dừng',
            'Bài thi của bạn đã bị giám thị tạm dừng. Vui lòng chờ.',
        );

        return response()->json([
            'message' => 'Đã tạm dừng bài thi.',
            'attempt' => $attempt->fresh()->load('user:id,name,email'),
        ]);
    }

    /**
     * Resume a paused attempt (admin/instructor action).
     */
    public function resumeAttempt(Request $request, QuizAttempt $attempt): JsonResponse
    {
        $this->authorizeProctor($request, $attempt);

        if (!$attempt->isPaused()) {
            return response()->json([
                'message' => 'Chỉ có thể tiếp tục bài thi đang tạm dừng.',
                'current_status' => $attempt->status,
            ], 422);
        }

        // Calculate paused duration to add
        $pausedSeconds = $attempt->paused_at
            ? now()->diffInSeconds($attempt->paused_at)
            : 0;

        $attempt->update([
            'status'          => 'in_progress',
            'resumed_at'      => now(),
            'paused_at'       => null,
            'paused_duration' => ($attempt->paused_duration ?? 0) + $pausedSeconds,
        ]);

        Notification::send(
            $attempt->user_id,
            'exam_resumed',
            'Bài thi đã được tiếp tục',
            'Bạn có thể tiếp tục làm bài thi.',
        );

        return response()->json([
            'message'        => 'Đã cho tiếp tục bài thi.',
            'paused_seconds' => $pausedSeconds,
            'attempt'        => $attempt->fresh()->load('user:id,name,email'),
        ]);
    }

    /**
     * Force-stop an attempt (violation / admin decision).
     */
    public function forceStopAttempt(Request $request, QuizAttempt $attempt): JsonResponse
    {
        $this->authorizeProctor($request, $attempt);

        if ($attempt->isCompleted()) {
            return response()->json([
                'message' => 'Bài thi đã hoàn thành, không thể dừng.',
            ], 422);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        // If paused, add paused time before stopping
        $pausedAdd = 0;
        if ($attempt->isPaused() && $attempt->paused_at) {
            $pausedAdd = now()->diffInSeconds($attempt->paused_at);
        }

        $attempt->update([
            'status'            => 'force_stopped',
            'completed_at'      => now(),
            'force_stop_reason' => $validated['reason'],
            'paused_duration'   => ($attempt->paused_duration ?? 0) + $pausedAdd,
            'paused_at'         => null,
        ]);

        Notification::send(
            $attempt->user_id,
            'exam_force_stopped',
            'Bài thi đã bị dừng',
            "Bài thi của bạn đã bị giám thị dừng. Lý do: {$validated['reason']}",
        );

        return response()->json([
            'message' => 'Đã dừng bài thi.',
            'attempt' => $attempt->fresh()->load('user:id,name,email'),
        ]);
    }

    /**
     * Extend time for an attempt.
     */
    public function extendTime(Request $request, QuizAttempt $attempt): JsonResponse
    {
        $this->authorizeProctor($request, $attempt);

        if ($attempt->isCompleted()) {
            return response()->json(['message' => 'Bài thi đã hoàn thành.'], 422);
        }

        $validated = $request->validate([
            'minutes' => ['required', 'integer', 'min:1', 'max:180'],
        ]);

        $extraSeconds = $validated['minutes'] * 60;

        $attempt->update([
            'time_extensions' => ($attempt->time_extensions ?? 0) + $extraSeconds,
        ]);

        Notification::send(
            $attempt->user_id,
            'exam_time_extended',
            'Gia hạn thời gian thi',
            "Bạn được gia hạn thêm {$validated['minutes']} phút.",
        );

        return response()->json([
            'message'         => "Đã gia hạn thêm {$validated['minutes']} phút.",
            'total_extensions' => $attempt->fresh()->time_extensions,
            'remaining_time'   => $attempt->fresh()->remainingTime(),
        ]);
    }

    // ── Auto-save (Student action) ──────────────────────────────────────

    /**
     * Auto-save answers during exam.
     */
    public function autoSave(Request $request, QuizAttempt $attempt): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $attempt->user_id === $user->id, 403);

        if ($attempt->isCompleted()) {
            return response()->json(['message' => 'Bài thi đã hoàn thành.'], 422);
        }

        if ($attempt->isPaused()) {
            return response()->json([
                'message' => 'Bài thi đang tạm dừng.',
                'status'  => 'paused',
            ], 422);
        }

        // Check time expired server-side
        if ($attempt->isTimeExpired()) {
            return response()->json([
                'message' => 'Đã hết giờ thi.',
                'status'  => 'time_expired',
            ], 422);
        }

        $validated = $request->validate([
            'answers' => ['required', 'array'],
        ]);

        $attempt->update([
            'answers_json'  => $validated['answers'],
            'auto_saved_at' => now(),
        ]);

        return response()->json([
            'message'        => 'Đã lưu tự động.',
            'auto_saved_at'  => $attempt->fresh()->auto_saved_at,
            'remaining_time' => $attempt->fresh()->remainingTime(),
            'status'         => $attempt->status,
        ]);
    }

    /**
     * Check attempt status (student polling).
     */
    public function checkStatus(Request $request, QuizAttempt $attempt): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $attempt->user_id === $user->id, 403);

        return response()->json([
            'status'         => $attempt->status,
            'remaining_time' => $attempt->remainingTime(),
            'paused_at'      => $attempt->paused_at,
            'time_expired'   => $attempt->isTimeExpired(),
            'violations_count' => $attempt->violations()->count(),
        ]);
    }

    // ── Violation logging (Student client reports) ──────────────────────

    /**
     * Log a violation from the client.
     */
    public function logViolation(Request $request, QuizAttempt $attempt): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $attempt->user_id === $user->id, 403);

        if ($attempt->isCompleted()) {
            return response()->json(['message' => 'Bài thi đã hoàn thành.'], 422);
        }

        $validated = $request->validate([
            'type'     => ['required', 'string', 'in:focus_lost,no_face,multiple_faces,suspicious,looking_away,phone_detected'],
            'severity' => ['nullable', 'string', 'in:warning,critical'],
            'metadata' => ['nullable', 'array'],
            'image'    => ['nullable', 'string'],
        ]);

        $snapshotUrl = null;
        if (!empty($validated['image'])) {
            $snapshotUrl = $this->storeViolationSnapshot($validated['image'], $attempt->id);
        }

        $violation = ExamViolation::create([
            'attempt_id'   => $attempt->id,
            'user_id'      => $user->id,
            'type'         => $validated['type'],
            'severity'     => $validated['severity'] ?? 'warning',
            'snapshot_url' => $snapshotUrl,
            'metadata'     => $validated['metadata'] ?? null,
        ]);

        // Notify instructor/admin about the violation
        $quiz = $attempt->quiz;
        $exam = $quiz?->exam;
        if ($exam && $exam->created_by) {
            Notification::send(
                $exam->created_by,
                'exam_violation',
                'Vi phạm trong kỳ thi',
                "Thí sinh {$user->name} vi phạm: {$validated['type']} trong bài thi \"{$exam->title}\".",
            );
        }

        return response()->json([
            'message'   => 'Violation logged.',
            'violation' => $violation,
        ]);
    }

    // ── Face verification (Student, before/during exam) ─────────────────

    /** similarity >= this passes. Tuned for face-api.js's FaceRecognitionNet
     *  euclidean distance (typical same-person distance < 0.5-0.6), converted
     *  client-side to similarity = 1 - distance before being sent here. */
    private const FACE_MATCH_THRESHOLD = 0.45;

    /**
     * Verify the student's captured face against their enrolled profile photo
     * before letting them start (or resume) a proctored exam.
     *
     * The actual face detection + descriptor comparison runs client-side
     * (face-api.js, in the browser) against the reference photo returned by
     * examPreCheck's `face_photo_url` — this endpoint receives the resulting
     * similarity score, re-validates it against a server-side threshold (so a
     * client can't just claim `ok: true`), and is the source of truth for
     * pass/fail plus violation logging.
     */
    public function verifyFace(Request $request, Exam $exam): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $validated = $request->validate([
            'image' => ['required', 'string'],
            'score' => ['required', 'numeric', 'min:0', 'max:1'],
        ]);

        // If the student already has an in-progress attempt for this exam,
        // failures here are real proctoring violations worth logging.
        $quiz = $exam->quiz;
        $attempt = $quiz
            ? QuizAttempt::where('user_id', $user->id)
                ->where('quiz_id', $quiz->id)
                ->whereIn('status', ['in_progress', 'paused'])
                ->latest()
                ->first()
            : null;

        if (empty($user->face_url)) {
            return response()->json([
                'ok'      => false,
                'message' => 'Chưa có ảnh khuôn mặt trên hồ sơ. Vui lòng liên hệ quản trị viên/giảng viên để cập nhật ảnh trước khi thi.',
            ], 422);
        }

        if (!$this->isValidCapturedImage($validated['image'])) {
            if ($attempt) {
                ExamViolation::create([
                    'attempt_id' => $attempt->id,
                    'user_id'    => $user->id,
                    'type'       => 'no_face',
                    'severity'   => 'warning',
                    'metadata'   => ['reason' => 'invalid_or_empty_capture'],
                ]);
            }

            return response()->json([
                'ok'      => false,
                'message' => 'Không nhận diện được khuôn mặt trong ảnh chụp. Vui lòng thử lại với đủ ánh sáng.',
            ], 422);
        }

        $score = (float) $validated['score'];
        $matched = $score >= self::FACE_MATCH_THRESHOLD;

        if (!$matched && $attempt) {
            ExamViolation::create([
                'attempt_id' => $attempt->id,
                'user_id'    => $user->id,
                'type'       => 'suspicious',
                'severity'   => 'warning',
                'metadata'   => ['reason' => 'face_mismatch', 'score' => $score],
            ]);
        }

        return response()->json([
            'ok'      => $matched,
            'score'   => $score,
            'message' => $matched
                ? 'Xác thực khuôn mặt thành công.'
                : 'Khuôn mặt không khớp với ảnh hồ sơ. Vui lòng thử lại với đủ ánh sáng và nhìn thẳng vào camera.',
        ]);
    }

    /**
     * Basic sanity check on a base64-encoded captured frame: must decode to a
     * non-trivial JPEG/PNG binary of reasonable size. This does not verify the
     * *identity* in the photo (that needs an ML model) — only that a real
     * camera frame was captured.
     */
    private function isValidCapturedImage(string $rawImage): bool
    {
        return $this->decodeValidatedImage($rawImage) !== null;
    }

    /**
     * Decodes + sanity-checks a base64-encoded captured frame (same rules as
     * isValidCapturedImage) and returns the raw binary + extension, or null
     * if it doesn't look like a real JPEG/PNG camera frame.
     */
    private function decodeValidatedImage(string $rawImage): ?array
    {
        $data = $rawImage;
        if (preg_match('/^data:image\/(jpe?g|png);base64,(.+)$/i', $data, $matches)) {
            $data = $matches[2];
        }

        $binary = base64_decode($data, true);
        if ($binary === false) {
            return null;
        }

        $size = strlen($binary);
        if ($size < 1000 || $size > 8 * 1024 * 1024) {
            return null;
        }

        $isJpeg = substr($binary, 0, 2) === "\xFF\xD8";
        $isPng  = substr($binary, 0, 8) === "\x89PNG\r\n\x1a\n";
        if (!$isJpeg && !$isPng) {
            return null;
        }

        return ['binary' => $binary, 'ext' => $isPng ? 'png' : 'jpg'];
    }

    /**
     * Persists a violation snapshot (base64 frame) to storage and returns its
     * public URL, or null if the image was invalid — a bad/missing snapshot
     * must never block the violation itself from being logged.
     */
    private function storeViolationSnapshot(string $rawImage, int $attemptId): ?string
    {
        $decoded = $this->decodeValidatedImage($rawImage);
        if (!$decoded) {
            return null;
        }

        $media = app(MediaService::class);
        $disk  = $media->getDisk();
        $path  = "exam-violations/{$attemptId}/" . now()->timestamp . '_' . Str::random(10) . '.' . $decoded['ext'];

        Storage::disk($disk)->put($path, $decoded['binary']);

        return $media->getUrl($path);
    }

    // ── Live Monitor (Admin/Instructor) ─────────────────────────────────

    /**
     * Get live monitoring data for an exam.
     */
    public function liveMonitor(Request $request, Exam $exam): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && (\App\Support\Authorize::isAdmin($user) || (int) $exam->created_by === (int) $user->id), 403);

        $quiz = $exam->quiz;
        if (!$quiz) {
            return response()->json(['message' => 'Exam chưa có quiz.'], 404);
        }

        $attempts = QuizAttempt::where('quiz_id', $quiz->id)
            ->with([
                'user:id,name,email',
                'violations' => fn ($q) => $q->latest()->limit(5),
            ])
            ->withCount('violations')
            ->orderByRaw("FIELD(status, 'in_progress', 'paused', 'submitted', 'force_stopped')")
            ->get()
            ->map(function ($attempt) {
                return [
                    'id'               => $attempt->id,
                    'user'             => $attempt->user,
                    'status'           => $attempt->status,
                    'started_at'       => $attempt->started_at,
                    'remaining_time'   => $attempt->remainingTime(),
                    'score'            => $attempt->score,
                    'passed'           => $attempt->passed,
                    'violations_count' => $attempt->violations_count,
                    'recent_violations'=> $attempt->violations,
                    'auto_saved_at'    => $attempt->auto_saved_at,
                    'ip_address'       => $attempt->ip_address,
                    'force_stop_reason'=> $attempt->force_stop_reason,
                ];
            });

        return response()->json([
            'exam'     => $exam->only(['id', 'title', 'status', 'duration', 'starts_at', 'ends_at']),
            'attempts' => $attempts,
            'summary'  => [
                'total'         => $attempts->count(),
                'in_progress'   => $attempts->where('status', 'in_progress')->count(),
                'paused'        => $attempts->where('status', 'paused')->count(),
                'submitted'     => $attempts->where('status', 'submitted')->count(),
                'force_stopped' => $attempts->where('status', 'force_stopped')->count(),
            ],
        ]);
    }

    // ── Authorization ───────────────────────────────────────────────────

    private function authorizeProctor(Request $request, QuizAttempt $attempt): void
    {
        $user = $request->user();
        abort_unless($user, 403);

        // Admin always allowed
        if (\App\Support\Authorize::isAdmin($user)) return;

        // Instructor: must own the exam's course or be exam creator
        $quiz = $attempt->quiz;
        $exam = $quiz?->exam;
        $course = $quiz?->course ?? $exam?->course;

        $isOwner = ($course && $course->user_id === $user->id)
                || ($exam && $exam->created_by === $user->id);

        abort_unless($isOwner, 403);
    }
}
