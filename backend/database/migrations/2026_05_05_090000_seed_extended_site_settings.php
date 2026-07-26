<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();
        $defaults = [
            // Theme Colors
            'theme_color_primary' => '#0F6E8C',
            'theme_color_deep'    => '#0b5167',

            // Branding
            'site_tagline'       => 'Học mọi lúc, mọi nơi',

            // Contact
            'contact_email'      => null,
            'contact_phone'      => null,
            'contact_address'    => null,
            'support_hours'      => 'Thứ 2 - Thứ 7, 8:00 - 17:30',

            // Social
            'social_facebook'    => null,
            'social_youtube'     => null,
            'social_tiktok'      => null,
            'social_linkedin'    => null,
            'social_zalo'        => null,

            // Legal / Footer
            'footer_copyright'   => '© ' . date('Y') . ' Eript LMS. All rights reserved.',
            'legal_company_name' => null,
            'legal_tax_code'     => null,
            'terms_url'          => null,
            'privacy_url'        => null,

            // Localization
            'default_locale'     => 'vi',
            'default_currency'   => 'VND',
            'timezone'           => 'Asia/Ho_Chi_Minh',
        ];

        $existingKeys = DB::table('site_settings')->pluck('key')->all();

        $rows = [];
        foreach ($defaults as $key => $value) {
            if (in_array($key, $existingKeys, true)) {
                continue;
            }
            $rows[] = [
                'key'        => $key,
                'value'      => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($rows)) {
            DB::table('site_settings')->insert($rows);
        }
    }

    public function down(): void
    {
        DB::table('site_settings')->whereIn('key', [
            'theme_color_primary', 'theme_color_deep',
            'site_tagline',
            'contact_email', 'contact_phone', 'contact_address', 'support_hours',
            'social_facebook', 'social_youtube', 'social_tiktok', 'social_linkedin', 'social_zalo',
            'footer_copyright', 'legal_company_name', 'legal_tax_code', 'terms_url', 'privacy_url',
            'default_locale', 'default_currency', 'timezone',
        ])->delete();
    }
};
