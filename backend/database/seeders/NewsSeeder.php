<?php

namespace Database\Seeders;

use App\Models\NewsPost;
use App\Models\User;
use Illuminate\Database\Seeder;

class NewsSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::role('admin')->first() ?? User::query()->first();

        $items = [
            [
                'title' => 'Hướng dẫn đăng ký học phần kỳ mới',
                'excerpt' => 'Sinh viên xem thời khóa biểu lớp hành chính và lịch thi trên portal.',
                'content' => "<p>Kỳ học mới đã mở. Sinh viên vui lòng:</p><ul><li>Kiểm tra thời khóa biểu</li><li>Theo dõi lịch thi cuối kỳ</li><li>Thanh toán học phí đúng hạn</li></ul>",
                'is_featured' => true,
            ],
            [
                'title' => 'Ra mắt AI Career & Cố vấn học tập',
                'excerpt' => 'Hai tính năng AI hỗ trợ định hướng nghề nghiệp và lộ trình CTĐT.',
                'content' => '<p>Hệ thống đã tích hợp AI Career (upload/form CV) và Cố vấn học tập dựa trên tiến trình CTĐT.</p>',
                'is_featured' => false,
            ],
            [
                'title' => 'Marketplace khóa bổ trợ',
                'excerpt' => 'Mua thêm khóa học ngoài CTĐT để củng cố kỹ năng.',
                'content' => '<p>Khóa marketplace có thể mua trực tiếp trên portal sinh viên.</p>',
                'is_featured' => false,
            ],
        ];

        foreach ($items as $item) {
            $slug = NewsPost::uniqueSlug($item['title']);
            NewsPost::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'author_id' => $author?->id,
                    'title' => $item['title'],
                    'excerpt' => $item['excerpt'],
                    'content' => $item['content'],
                    'status' => 'published',
                    'is_featured' => $item['is_featured'],
                    'published_at' => now()->subDays(rand(1, 10)),
                ]
            );
        }
    }
}
