<?php

namespace App\Http\Controllers\UserManagement;

use App\Helpers\GpaCalculator;
use App\Http\Controllers\Controller;
use App\Models\ClassSection;
use App\Models\OfflineSession;
use App\Models\OfflineSessionAttendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfflineSessionController extends Controller
{
    // ─── Instructor: list sessions for a class section ───────────────────────
    public function index(Request $request, ClassSection $classSection): JsonResponse
    {
        $sessions = OfflineSession::where('class_section_id', $classSection->id)
            ->withCount('attendances')
            ->orderBy('start_at', 'desc')
            ->get();

        return response()->json(['sessions' => $sessions]);
    }

    // ─── Instructor: create session ───────────────────────────────────────────
    public function store(Request $request, ClassSection $classSection): JsonResponse
    {
        $data = $request->validate([
            'title'            => 'required|string|max:255',
            'location'         => 'required|string|max:255',
            'start_at'         => 'required|date',
            'duration'         => 'required|integer|min:1',
            'max_participants' => 'nullable|integer|min:1',
            'latitude'         => 'required|numeric|between:-90,90',
            'longitude'        => 'required|numeric|between:-180,180',
            'check_in_radius_meters' => 'nullable|integer|min:5|max:500',
        ]);

        $session = OfflineSession::create([
            ...$data,
            'check_in_radius_meters' => $data['check_in_radius_meters'] ?? OfflineSession::DEFAULT_CHECK_IN_RADIUS_METERS,
            'class_section_id' => $classSection->id,
            'is_active'        => false,
        ]);

        return response()->json(['session' => $session], 201);
    }

    // ─── Instructor: update session ───────────────────────────────────────────
    public function update(Request $request, OfflineSession $session): JsonResponse
    {
        $data = $request->validate([
            'title'            => 'sometimes|string|max:255',
            'location'         => 'sometimes|string|max:255',
            'start_at'         => 'sometimes|date',
            'duration'         => 'sometimes|integer|min:1',
            'max_participants' => 'nullable|integer|min:1',
            'latitude'         => 'sometimes|numeric|between:-90,90',
            'longitude'        => 'sometimes|numeric|between:-180,180',
            'check_in_radius_meters' => 'sometimes|integer|min:5|max:500',
            'is_active'        => 'sometimes|boolean',
        ]);

        $session->update($data);

        return response()->json(['session' => $session->fresh()]);
    }

    // ─── Instructor: delete session ───────────────────────────────────────────
    public function destroy(OfflineSession $session): JsonResponse
    {
        $session->delete();
        return response()->json(['message' => 'Đã xoá phiên học.']);
    }

    // ─── Instructor: generate / refresh QR code ───────────────────────────────
    public function generateQr(Request $request, OfflineSession $session): JsonResponse
    {
        $ttl = (int) $request->input('ttl_minutes', 5);
        $session->generateQrToken($ttl);

        // Opening QR implies attendance is open.
        if (!$session->is_active) {
            $session->is_active = true;
            $session->save();
        }

        return response()->json([
            'qr_token'      => $session->qr_token,
            'qr_expires_at' => $session->qr_expires_at->toIso8601String(),
            'qr_payload'    => $session->qrPayload(),
            'check_in_radius_meters' => $session->checkInRadiusMeters(),
            'session' => $session->fresh(),
        ]);
    }

    // ─── Instructor: attendance report for a session ─────────────────────────
    public function attendanceReport(OfflineSession $session): JsonResponse
    {
        $section = $session->classSection()->with('enrollments.user')->first();

        $attendances = OfflineSessionAttendance::where('offline_session_id', $session->id)
            ->with('user:id,name,student_code,email,avatar')
            ->get()
            ->keyBy('user_id');

        $enrolled = $section
            ? $section->enrollments->map(fn ($e) => $e->user)->filter()
            : collect();

        $rows = $enrolled->map(function ($user) use ($attendances) {
            $att = $attendances->get($user->id);
            return [
                'user_id'         => $user->id,
                'name'            => $user->name,
                'student_code'    => $user->student_code,
                'email'           => $user->email,
                'avatar'          => $user->avatar,
                'status'          => $att?->status ?? 'absent',
                'checked_in_at'   => $att?->checked_in_at?->toIso8601String(),
                'distance_meters' => $att?->distance_meters,
            ];
        })->values();

        $summary = [
            'total'   => $rows->count(),
            'present' => $rows->where('status', 'present')->count(),
            'late'    => $rows->where('status', 'late')->count(),
            'absent'  => $rows->where('status', 'absent')->count(),
        ];

        return response()->json([
            'session' => $session,
            'summary' => $summary,
            'rows'    => $rows,
        ]);
    }

    // ─── Instructor: full attendance stats across all sessions of a section ──
    public function sectionStats(ClassSection $classSection): JsonResponse
    {
        $sessions = OfflineSession::where('class_section_id', $classSection->id)
            ->orderBy('start_at')
            ->get();

        $section = $classSection->load('enrollments.user');
        $enrolled = $section->enrollments->map(fn ($e) => $e->user)->filter()->unique('id');

        $allAttendances = OfflineSessionAttendance::whereIn('offline_session_id', $sessions->pluck('id'))
            ->get();

        $students = $enrolled->map(function ($user) use ($sessions, $allAttendances) {
            $userAtts = $allAttendances->where('user_id', $user->id);
            $present  = $userAtts->where('status', 'present')->count();
            $late     = $userAtts->where('status', 'late')->count();
            $absent   = $sessions->count() - $present - $late;

            $rate = $sessions->count() > 0
                ? round(($present + $late) / $sessions->count() * 100, 1)
                : 0;

            return [
                'user_id'        => $user->id,
                'name'           => $user->name,
                'student_code'   => $user->student_code,
                'total_sessions' => $sessions->count(),
                'present'        => $present,
                'late'           => $late,
                'absent'         => $absent,
                'attendance_rate' => $rate,
            ];
        })->values();

        return response()->json([
            'class_section'  => $classSection->only(['id', 'code', 'name']),
            'sessions_count' => $sessions->count(),
            'students'       => $students,
        ]);
    }
}
