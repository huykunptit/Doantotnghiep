<?php

namespace Database\Seeders;

use App\Models\CertificateTemplate;
use App\Models\Course;
use Illuminate\Database\Seeder;

/**
 * Seed 4 template chứng chỉ hoàn thành khóa học với fields_config đầy đủ.
 * Gán template phù hợp cho các extension courses.
 *
 * Chạy độc lập: php artisan db:seed --class=CertificateTemplateSeeder
 */
class CertificateTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = $this->seedTemplates();
        $this->assignTemplatesToCourses($templates);

        $this->command?->info('CertificateTemplateSeeder: ' . count($templates) . ' template(s) đã được seed.');
    }

    private function seedTemplates(): array
    {
        $defaultFields = $this->defaultFieldsConfig();

        $definitions = [
            [
                'name'                 => 'Chứng chỉ Hoàn thành Khóa học',
                'background_image_url' => 'https://images.unsplash.com/photo-1523050854058-8df90110c9f1?w=1600&q=90',
                'fields_config'        => $defaultFields,
            ],
            [
                'name'                 => 'Chứng nhận Xuất sắc',
                'background_image_url' => 'https://images.unsplash.com/photo-1635070041078-e363dbe005cb?w=1600&q=90',
                'fields_config'        => $this->excellenceFieldsConfig(),
            ],
            [
                'name'                 => 'Chứng chỉ Chính quy PTIT',
                'background_image_url' => 'https://images.unsplash.com/photo-1541339907198-e08756dedf3f?w=1600&q=90',
                'fields_config'        => $this->academicFieldsConfig(),
            ],
            [
                'name'                 => 'Chứng nhận Kỹ năng Nghề',
                'background_image_url' => 'https://images.unsplash.com/photo-1507679799987-c73779587ccf?w=1600&q=90',
                'fields_config'        => $this->professionalFieldsConfig(),
            ],
        ];

        $created = [];
        foreach ($definitions as $def) {
            $created[] = CertificateTemplate::query()->updateOrCreate(
                ['name' => $def['name']],
                $def
            );
        }

        return $created;
    }

    private function assignTemplatesToCourses(array $templates): void
    {
        // Template 0: khóa extension thông thường
        // Template 1: khóa "chuyên sâu"
        // Template 2: khóa core/chính quy
        // Template 3: khóa "thực chiến" / dự án
        $map = [
            'nhập môn'   => $templates[2]->id,   // core → chính quy
            'thực chiến' => $templates[3]->id,   // thực chiến → kỹ năng nghề
            'chuyên sâu' => $templates[1]->id,   // chuyên sâu → xuất sắc
            'dự án'      => $templates[0]->id,   // dự án → tiêu chuẩn
        ];

        Course::query()->where('status', 'published')->get()->each(function (Course $course) use ($map, $templates) {
            $title = mb_strtolower($course->title);
            foreach ($map as $keyword => $templateId) {
                if (str_contains($title, $keyword)) {
                    $course->update(['certificate_template_id' => $templateId]);
                    return;
                }
            }
            // Fallback: template 0
            $course->update(['certificate_template_id' => $templates[0]->id]);
        });
    }

    // ── Fields config helpers ────────────────────────────────────────────────

    private function defaultFieldsConfig(): array
    {
        return [
            ['key' => 'student_name', 'label' => 'Tên học viên', 'x' => 50, 'y' => 42, 'font_size' => 36, 'font_family' => 'Georgia, serif',      'color' => '#1a1a1a', 'font_weight' => 'bold',   'text_align' => 'center', 'visible' => true],
            ['key' => 'course_title', 'label' => 'Tên khoá học', 'x' => 50, 'y' => 55, 'font_size' => 18, 'font_family' => 'Arial, sans-serif',    'color' => '#444444', 'font_weight' => 'normal', 'text_align' => 'center', 'visible' => true],
            ['key' => 'issued_date',  'label' => 'Ngày cấp',     'x' => 50, 'y' => 68, 'font_size' => 13, 'font_family' => 'Arial, sans-serif',    'color' => '#666666', 'font_weight' => 'normal', 'text_align' => 'center', 'visible' => true],
            ['key' => 'credential_id','label' => 'Mã xác nhận',  'x' => 50, 'y' => 78, 'font_size' => 11, 'font_family' => 'Courier New, monospace','color' => '#888888', 'font_weight' => 'normal', 'text_align' => 'center', 'visible' => true],
        ];
    }

    private function excellenceFieldsConfig(): array
    {
        return [
            ['key' => 'student_name', 'label' => 'Tên học viên', 'x' => 50, 'y' => 40, 'font_size' => 40, 'font_family' => 'Georgia, serif',   'color' => '#b8860b', 'font_weight' => 'bold',   'text_align' => 'center', 'visible' => true],
            ['key' => 'course_title', 'label' => 'Tên khoá học', 'x' => 50, 'y' => 54, 'font_size' => 20, 'font_family' => 'Arial, sans-serif', 'color' => '#333333', 'font_weight' => 'bold',   'text_align' => 'center', 'visible' => true],
            ['key' => 'issued_date',  'label' => 'Ngày cấp',     'x' => 30, 'y' => 70, 'font_size' => 13, 'font_family' => 'Arial, sans-serif', 'color' => '#555555', 'font_weight' => 'normal', 'text_align' => 'center', 'visible' => true],
            ['key' => 'credential_id','label' => 'Mã xác nhận',  'x' => 70, 'y' => 70, 'font_size' => 11, 'font_family' => 'Courier New, monospace','color' => '#888888','font_weight' => 'normal','text_align' => 'center', 'visible' => true],
        ];
    }

    private function academicFieldsConfig(): array
    {
        return [
            ['key' => 'student_name', 'label' => 'Tên sinh viên','x' => 50, 'y' => 44, 'font_size' => 34, 'font_family' => 'Times New Roman, serif','color' => '#1a3a6b','font_weight' => 'bold',  'text_align' => 'center', 'visible' => true],
            ['key' => 'course_title', 'label' => 'Học phần',      'x' => 50, 'y' => 56, 'font_size' => 17, 'font_family' => 'Times New Roman, serif','color' => '#2c3e50','font_weight' => 'normal','text_align' => 'center', 'visible' => true],
            ['key' => 'issued_date',  'label' => 'Ngày cấp',      'x' => 50, 'y' => 67, 'font_size' => 13, 'font_family' => 'Arial, sans-serif',    'color' => '#555555','font_weight' => 'normal','text_align' => 'center', 'visible' => true],
            ['key' => 'credential_id','label' => 'Mã chứng nhận', 'x' => 50, 'y' => 76, 'font_size' => 10, 'font_family' => 'Courier New, monospace','color' => '#999999','font_weight' => 'normal','text_align' => 'center', 'visible' => true],
        ];
    }

    private function professionalFieldsConfig(): array
    {
        return [
            ['key' => 'student_name', 'label' => 'Tên học viên','x' => 50, 'y' => 38, 'font_size' => 38, 'font_family' => 'Georgia, serif',     'color' => '#ffffff','font_weight' => 'bold',  'text_align' => 'center', 'visible' => true],
            ['key' => 'course_title', 'label' => 'Kỹ năng',     'x' => 50, 'y' => 52, 'font_size' => 20, 'font_family' => 'Arial, sans-serif',  'color' => '#f0f0f0','font_weight' => 'bold',  'text_align' => 'center', 'visible' => true],
            ['key' => 'issued_date',  'label' => 'Ngày cấp',    'x' => 50, 'y' => 65, 'font_size' => 13, 'font_family' => 'Arial, sans-serif',  'color' => '#dddddd','font_weight' => 'normal','text_align' => 'center', 'visible' => true],
            ['key' => 'credential_id','label' => 'Mã xác nhận', 'x' => 50, 'y' => 75, 'font_size' => 11, 'font_family' => 'Courier New, monospace','color' => '#cccccc','font_weight' => 'normal','text_align' => 'center','visible' => true],
        ];
    }
}

