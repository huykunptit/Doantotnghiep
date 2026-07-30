<?php

namespace App\Http\Controllers\CourseManagement;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\OfflineSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonOfflineSessionController extends Controller
{
    public function show(Course $course, Lesson $lesson): JsonResponse
    {
        if ($lesson->course_id !== $course->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $session = $lesson->offlineSession;
        if (! $session) {
            return response()->json(['message' => 'No offline session for this lesson'], 404);
        }

        return response()->json($session);
    }

    public function store(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (! \App\Support\Authorize::isAdmin($user) && (int) $course->user_id !== (int) $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($lesson->course_id !== $course->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'room' => ['nullable', 'string', 'max:100'],
            'start_at' => ['required', 'date'],
            'duration' => ['required', 'integer', 'min:15'],
            'max_participants' => ['nullable', 'integer', 'min:1', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'check_in_radius_meters' => ['nullable', 'integer', 'min:5', 'max:500'],
            'qr_enabled' => ['nullable', 'boolean'],
            'qr_mode' => ['nullable', 'in:manual,rotating,static'],
            'qr_rotate_seconds' => ['nullable', 'integer', 'min:15', 'max:600'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $qrEnabled = (bool) ($validated['qr_enabled'] ?? false);
        if ($qrEnabled && (empty($validated['latitude']) || empty($validated['longitude']))) {
            return response()->json([
                'message' => 'Bật điểm danh QR cần tọa độ (vĩ độ / kinh độ) của địa điểm học.',
            ], 422);
        }

        $session = OfflineSession::updateOrCreate(
            ['lesson_id' => $lesson->id],
            [
                'title' => $validated['title'] ?? $lesson->title,
                'location' => $validated['location'],
                'room' => $validated['room'] ?? null,
                'start_at' => $validated['start_at'],
                'duration' => $validated['duration'],
                'max_participants' => $validated['max_participants'] ?? null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'check_in_radius_meters' => $validated['check_in_radius_meters']
                    ?? OfflineSession::DEFAULT_CHECK_IN_RADIUS_METERS,
                'qr_enabled' => $qrEnabled,
                'qr_mode' => $validated['qr_mode'] ?? OfflineSession::QR_MODE_MANUAL,
                'qr_rotate_seconds' => $validated['qr_rotate_seconds'] ?? 60,
                'is_active' => array_key_exists('is_active', $validated)
                    ? (bool) $validated['is_active']
                    : true,
            ],
        );

        if ($lesson->type !== 'offline') {
            $lesson->update([
                'type' => 'offline',
                'duration' => ($validated['duration'] ?? 0) * 60,
            ]);
        }

        return response()->json([
            'message' => 'Offline session updated',
            'offline_session' => $session->fresh(),
        ]);
    }

    public function generateQr(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (! \App\Support\Authorize::isAdmin($user) && (int) $course->user_id !== (int) $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($lesson->course_id !== $course->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $session = $lesson->offlineSession;
        if (! $session) {
            return response()->json(['message' => 'Chưa cấu hình buổi offline cho bài học này.'], 404);
        }

        if (! $session->qr_enabled) {
            return response()->json(['message' => 'Điểm danh QR chưa được bật cho buổi học này.'], 422);
        }

        if ($session->latitude === null || $session->longitude === null) {
            return response()->json(['message' => 'Thiếu tọa độ địa điểm — không thể mở QR điểm danh.'], 422);
        }

        $ttlMinutes = (int) $request->input('ttl_minutes', 5);
        $session->generateQrToken($ttlMinutes);
        if (! $session->is_active) {
            $session->is_active = true;
            $session->save();
        }

        return response()->json([
            'qr_token' => $session->qr_token,
            'qr_expires_at' => $session->qr_expires_at?->toIso8601String(),
            'qr_payload' => $session->qrPayload(),
            'qr_mode' => $session->normalizedQrMode(),
            'qr_rotate_seconds' => $session->rotateIntervalSeconds(),
            'check_in_radius_meters' => $session->checkInRadiusMeters(),
            'session' => $session->fresh(),
        ]);
    }

    public function destroy(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (! \App\Support\Authorize::isAdmin($user) && (int) $course->user_id !== (int) $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($lesson->course_id !== $course->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $lesson->offlineSession()?->delete();

        return response()->json(['message' => 'Offline session removed']);
    }
}
