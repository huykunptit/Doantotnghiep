<?php

namespace App\Http\Controllers\QuizManagement;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamViolation;
use App\Models\Notification;
use App\Models\QuizAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'type'     => ['required', 'string', 'in:focus_lost,no_face,multiple_faces,suspicious'],
            'severity' => ['nullable', 'string', 'in:warning,critical'],
            'metadata' => ['nullable', 'array'],
        ]);

        $violation = ExamViolation::create([
            'attempt_id'   => $attempt->id,
            'user_id'      => $user->id,
            'type'         => $validated['type'],
            'severity'     => $validated['severity'] ?? 'warning',
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

    // ── Live Monitor (Admin/Instructor) ─────────────────────────────────

    /**
     * Get live monitoring data for an exam.
     */
    public function liveMonitor(Request $request, Exam $exam): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && ($user->hasRole('admin') || $exam->created_by === $user->id), 403);

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
        if ($user->hasRole('admin')) return;

        // Instructor: must own the exam's course or be exam creator
        $quiz = $attempt->quiz;
        $exam = $quiz?->exam;
        $course = $quiz?->course ?? $exam?->course;

        $isOwner = ($course && $course->user_id === $user->id)
                || ($exam && $exam->created_by === $user->id);

        abort_unless($isOwner, 403);
    }
}
