<?php

namespace Database\Seeders;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seed 20 thông báo mẫu, trải đều cho admin / instructor / student.
 * Types: enrollment, order, course_approved, course_rejected, review, system
 *
 * Chạy độc lập: php artisan db:seed --class=NotificationSeeder
 */
class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $admin      = User::query()->where('email', 'admin@lms.com')->first();
        $instructors = User::query()->where('email', 'like', 'instructor%@lms.com')->orderBy('id')->get();
        $students    = User::role('student')->orderBy('id')->get();

        if (!$admin || $instructors->isEmpty() || $students->isEmpty()) {
            $this->command?->warn('NotificationSeeder: thiếu user, bỏ qua.');
            return;
        }

        $ins1 = $instructors->first();
        $ins2 = $instructors->get(1) ?? $ins1;
        $sv1  = $students->first();
        $sv2  = $students->get(1) ?? $sv1;
        $sv3  = $students->get(2) ?? $sv1;
        $sv4  = $students->get(3) ?? $sv1;
        $sv5  = $students->get(4) ?? $sv1;

        $blueprints = [
            // ── System (admin) ───────────────────────────────────────────────
            [$admin->id,  'system',           'Hệ thống đã khởi động',           'PTIT LMS đã hoàn tất khởi động và sẵn sàng hoạt động.',                  '/admin/dashboard',             true],
            [$admin->id,  'system',           'Backup dữ liệu thành công',        'Bản sao lưu tự động lúc 02:00 đã hoàn thành không có lỗi.',               '/admin/settings',              true],
            [$admin->id,  'system',           'Người dùng mới đăng ký',           '5 tài khoản mới đã đăng ký trong 24 giờ qua.',                           '/admin/users',                 false],
            [$admin->id,  'course_approved',  'Khóa học đã được duyệt',           '"Thực chiến Lập trình & CNTT" đã được phê duyệt và xuất bản.',            '/admin/courses',               false],
            [$admin->id,  'order',            'Doanh thu tháng này vượt mốc',     'Tổng doanh thu tháng đạt 15,000,000 VNĐ — tăng 12% so với tháng trước.', '/admin/finances',              false],

            // ── Course approved/rejected (instructor) ────────────────────────
            [$ins1->id,   'course_approved',  'Khóa học được duyệt',              'Khóa học "Nhập môn Lập trình & CNTT" của bạn đã được phê duyệt.',         '/instructor/courses',          false],
            [$ins1->id,   'review',           'Đánh giá mới từ học viên',         'Học viên Sinh viên 1 vừa để lại đánh giá 5 sao cho khóa học của bạn.',    '/instructor/courses',          false],
            [$ins1->id,   'enrollment',       'Học viên mới ghi danh',            '3 học viên mới vừa ghi danh vào "Thực chiến Lập trình & CNTT".',          '/instructor/courses',          true],
            [$ins2->id,   'course_rejected',  'Khóa học bị từ chối duyệt',        'Khóa học "Chuyên sâu Thiết kế" cần chỉnh sửa trước khi được xuất bản.',   '/instructor/courses',          false],
            [$ins2->id,   'system',           'Bài kiểm tra sắp đến hạn',         '12 học viên chưa nộp bài tập tổng hợp trong khóa "Chuyên sâu Thiết kế".', '/instructor/courses',          false],

            // ── Enrollment & order (student) ─────────────────────────────────
            [$sv1->id,    'enrollment',       'Ghi danh thành công',              'Bạn đã ghi danh vào "Thực chiến Lập trình & CNTT". Chúc bạn học tốt!',    '/my-learning',                 true],
            [$sv1->id,    'order',            'Thanh toán thành công',            'Đơn hàng #PAY-0001 đã được xác nhận. Khóa học đã mở khóa.',               '/my-learning',                 true],
            [$sv2->id,    'enrollment',       'Ghi danh học phần chính quy',      'Bạn đã được ghi danh vào học phần "Nhập môn Kinh doanh" kỳ HK1/2025.',    '/my-learning',                 false],
            [$sv2->id,    'system',           'Nhắc nhở hoàn thành bài tập',      'Bài tập "Assignment tổng hợp kỹ năng" của khóa sẽ đến hạn sau 3 ngày.',   '/my-learning',                 false],
            [$sv3->id,    'order',            'Hoàn tiền được xử lý',             'Yêu cầu hoàn tiền đơn #PAY-0045 đã được xử lý và hoàn vào tài khoản.',    '/profile/orders',              true],
            [$sv3->id,    'review',           'Cảm ơn đánh giá của bạn',          'Đánh giá của bạn cho "Dự án cuối khóa Ngoại ngữ" đã được đăng thành công.','/courses',                    true],
            [$sv4->id,    'system',           'Chứng chỉ đã sẵn sàng',            'Chứng chỉ hoàn thành khóa "Nhập môn Lập trình & CNTT" đã được cấp.',      '/certificates',                false],
            [$sv4->id,    'enrollment',       'Lớp học phần mới được mở',         'Lớp học phần HK1/2025 môn "Nhập môn Ngoại ngữ" đã mở đăng ký.',          '/my-learning',                 false],
            [$sv5->id,    'system',           'Quiz sắp hết hạn',                 '"Quiz tổng hợp cuối khóa" của "Thực chiến Web Dev" sẽ đóng sau 24 giờ.',   '/my-learning',                 false],
            [$sv5->id,    'course_approved',  'Khóa học bạn quan tâm có cập nhật','Khóa "Chuyên sâu DevOps & Cloud" vừa thêm 2 bài học mới.',               '/courses',                     false],
        ];

        $created = 0;
        foreach ($blueprints as [$userId, $type, $title, $message, $link, $isRead]) {
            $existing = Notification::query()
                ->where('user_id', $userId)
                ->where('title', $title)
                ->exists();

            if (!$existing) {
                Notification::query()->create([
                    'user_id' => $userId,
                    'type'    => $type,
                    'title'   => $title,
                    'message' => $message,
                    'link'    => $link,
                    'read_at' => $isRead ? now()->subHours(rand(1, 48)) : null,
                ]);
                $created++;
            }
        }

        $this->command?->info("NotificationSeeder: {$created} thông báo mới đã được tạo (trong tổng số " . count($blueprints) . ').');
    }
}

