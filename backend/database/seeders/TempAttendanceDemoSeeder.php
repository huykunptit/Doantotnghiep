<?php

namespace Database\Seeders;

use App\Models\Enrollment;
use App\Models\OfflineSession;
use App\Models\OfflineSessionAttendance;
use Illuminate\Database\Seeder;

/**
 * Temporary demo data for the mobile QR attendance screen — NOT part of the
 * regular seed pipeline (not registered in DatabaseSeeder). Run manually:
 *   php artisan db:seed --class=TempAttendanceDemoSeeder
 *
 * Creates one "open for check-in" OfflineSession (no geofence, so it works
 * from any real device location) plus one pre-existing attendance record so
 * both the scan flow and the history tab have something to show for a
 * screenshot-based report.
 */
class TempAttendanceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $enrollment = Enrollment::whereNotNull('class_section_id')->first();

        if (!$enrollment) {
            $this->command->error('Không tìm thấy enrollment nào có class_section_id. Hãy chạy seed dữ liệu học thuật trước.');
            return;
        }

        $classSectionId = $enrollment->class_section_id;
        $targetUserId = $enrollment->user_id;

        // `lesson_id` predates class_section_id in this table and is still
        // NOT NULL at the DB level — pick any lesson under the same course so
        // the enrollment fallback check in checkIn() also has a valid path.
        $lessonId = \App\Models\Lesson::whereHas(
            'section',
            fn ($q) => $q->where('course_id', $enrollment->course_id)
        )->value('id');

        if (!$lessonId) {
            $this->command->error("Không tìm thấy lesson nào cho course_id={$enrollment->course_id}.");
            return;
        }

        $session = OfflineSession::updateOrCreate(
            ['title' => '[DEMO] Điểm danh QR - báo cáo đồ án'],
            [
                'lesson_id' => $lessonId,
                'class_section_id' => $classSectionId,
                'location' => 'Phòng demo (seed tạm)',
                'room' => 'DEMO-01',
                'start_at' => now()->subMinutes(5),
                'duration' => 90,
                'max_participants' => null,
                // Left null on purpose: checkIn() skips the geofence check
                // entirely when session lat/lng are null, so this works from
                // whatever real location the phone reports.
                'latitude' => null,
                'longitude' => null,
                'is_active' => true,
                'qr_enabled' => true,
                'qr_mode' => OfflineSession::QR_MODE_MANUAL,
            ]
        );

        // 2-hour TTL — generous window to demo + screenshot without the QR expiring mid-report.
        $session->generateQrToken(120);

        // Pre-seed one other enrolled student's attendance so the History tab
        // isn't empty either, without touching the target student's own record
        // (they should still be able to demo a fresh live check-in).
        $otherEnrollment = Enrollment::where('class_section_id', $classSectionId)
            ->where('user_id', '!=', $targetUserId)
            ->first();

        if ($otherEnrollment) {
            OfflineSessionAttendance::updateOrCreate(
                ['user_id' => $otherEnrollment->user_id, 'offline_session_id' => $session->id],
                [
                    'status' => 'present',
                    'checked_in_at' => now()->subMinutes(3),
                    'device_info' => 'seed-demo',
                    'latitude' => null,
                    'longitude' => null,
                    'distance_meters' => null,
                ]
            );
        }

        $this->command->info("Đã tạo session demo #{$session->id} cho class_section_id={$classSectionId}.");
        $this->command->info("Sinh viên demo (chưa điểm danh, dùng để quét/dán token): user_id={$targetUserId}.");
        $this->command->info("QR token (dán thủ công trong app nếu không quét camera được): {$session->qr_token}");
        $this->command->info("Token hết hạn lúc: {$session->qr_expires_at}");
    }
}
