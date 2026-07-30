<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seed toàn bộ site_settings.
 * Giữ nguyên tên site (Eript LMS), màu chủ đạo (#0f766e / #0d655e),
 * logo PTIT và các thông tin liên hệ mặc định.
 *
 * Idempotent: chỉ insert key chưa tồn tại.
 * Chạy độc lập: php artisan db:seed --class=SiteSettingsSeeder
 */
class SiteSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // ── Branding ──────────────────────────────────────────────────────
            'site_name'            => 'Eript LMS',
            'site_description'     => 'Nền tảng học tập trực tuyến – Học viện Công nghệ Bưu chính Viễn thông',
            'site_tagline'         => 'Học mọi lúc, mọi nơi',
            'site_logo'            => '/logo.png',
            'site_favicon'         => '/favicon.ico',

            // ── Theme Colors ──────────────────────────────────────────────────
            'theme_color_primary'  => '#0f766e',
            'theme_color_deep'     => '#0d655e',

            // ── Contact ───────────────────────────────────────────────────────
            'contact_email'        => 'namtd@ptit.edu.vn',
            'contact_phone'        => '024-35746799',
            'contact_address'      => 'Số 122 Hoàng Quốc Việt, phường Nghĩa Đô, thành phố Hà Nội',
            'support_hours'        => 'Thứ 2 – Thứ 7, 8:00 – 17:30',

            // ── Social ────────────────────────────────────────────────────────
            'social_facebook'      => 'https://www.facebook.com/PTIT.edu.vn',
            'social_youtube'       => 'https://www.youtube.com/@PTITedu',
            'social_tiktok'        => null,
            'social_linkedin'      => null,
            'social_zalo'          => null,

            // ── Legal / Footer ────────────────────────────────────────────────
            'footer_copyright'     => '© ' . date('Y') . ' Eript LMS – PTIT. All rights reserved.',
            'legal_company_name'   => 'Học viện Công nghệ Bưu chính Viễn thông',
            'legal_tax_code'       => null,
            'terms_url'            => null,
            'privacy_url'          => null,

            // ── Localization ──────────────────────────────────────────────────
            'default_locale'       => 'vi',
            'default_currency'     => 'VND',
            'timezone'             => 'Asia/Ho_Chi_Minh',

            // ── SMTP (giữ null, cấu hình qua .env thực tế) ───────────────────
            'smtp_host'            => null,
            'smtp_port'            => '587',
            'smtp_username'        => null,
            'smtp_password'        => null,
            'smtp_encryption'      => 'tls',
            'smtp_from_address'    => null,
            'smtp_from_name'       => 'Eript LMS',
        ];

        $existingKeys = DB::table('site_settings')->pluck('value', 'key')->all();
        $now = now();
        $inserts = [];
        $updates = 0;

        foreach ($settings as $key => $value) {
            if (!array_key_exists($key, $existingKeys)) {
                $inserts[] = [
                    'key'        => $key,
                    'value'      => $value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            // Key đã tồn tại → giữ nguyên (không override giá trị admin đã thay đổi)
        }

        if (!empty($inserts)) {
            DB::table('site_settings')->insert($inserts);
            $updates = count($inserts);
        }

        $this->command?->info("SiteSettingsSeeder: {$updates} setting(s) mới được thêm vào.");
    }
}

