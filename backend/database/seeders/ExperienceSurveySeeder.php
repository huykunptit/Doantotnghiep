<?php

namespace Database\Seeders;

use App\Models\AdministrativeClass;
use App\Models\ExperienceSurveyResponse;
use App\Models\Major;
use App\Models\User;
use App\Support\ExperienceSurveyDefinition;
use Illuminate\Database\Seeder;

/**
 * Fake khảo sát trải nghiệm từ SV đã có trên hệ thống (UserSeeder + OrgAcademic).
 * Không tạo thêm user. Dữ liệu nghiêng về sử dụng AI Career.
 */
class ExperienceSurveySeeder extends Seeder
{
    public function run(): void
    {
        $majorsByProgram = Major::query()->get()->groupBy('program_id');
        $classes = AdministrativeClass::query()
            ->with(['program', 'cohort'])
            ->get()
            ->keyBy('id');

        $students = User::role('student')
            ->whereNotNull('administrative_class_id')
            ->orderBy('id')
            ->get();

        $responses = 0;
        foreach ($students as $idx => $user) {
            $class = $classes->get($user->administrative_class_id);
            if (!$class) {
                continue;
            }

            ExperienceSurveyResponse::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'survey_version' => ExperienceSurveyDefinition::VERSION,
                    'answers' => $this->fakeAnswers($user, $class, $majorsByProgram, $idx),
                    'submitted_at' => now()
                        ->subDays(random_int(0, 18))
                        ->subHours(random_int(0, 20))
                        ->subMinutes(random_int(0, 59)),
                ]
            );
            $responses++;
        }

        $this->command?->info("ExperienceSurveySeeder: {$responses} survey responses from existing students (AI Career–biased).");
    }

    /**
     * @param  \Illuminate\Support\Collection  $majorsByProgram
     * @return array<string, mixed>
     */
    private function fakeAnswers(User $user, AdministrativeClass $class, $majorsByProgram, int $idx): array
    {
        $startYear = (int) ($class->cohort?->start_year ?? 0);
        if ($startYear < 2000) {
            $startYear = preg_match('/D(\d{2})/', (string) $class->code, $m)
                ? 2000 + (int) $m[1]
                : 2024;
        }
        $studyYear = max(1, min(4, (int) date('Y') - $startYear + 1));
        $a1 = match ($studyYear) {
            1 => 'year1',
            2 => 'year2',
            3 => 'year3',
            default => 'year4',
        };
        if (random_int(1, 10) === 1) {
            $a1 = ['year1', 'year2', 'year3', 'year4', 'other'][random_int(0, 4)];
        }

        $majors = $majorsByProgram->get($class->program_id, collect());
        $majorName = $user->major_id
            ? (Major::find($user->major_id)?->name)
            : ($majors->isEmpty() ? ($class->program?->name ?? 'CNTT') : $majors[$idx % $majors->count()]->name);

        $a3 = $this->pickWeighted(['lt1w' => 1, '1_4w' => 4, '1_3m' => 5, 'gt3m' => 2]);
        $a4 = $this->pickWeighted(['daily' => 3, 'weekly' => 5, 'monthly' => 2, 'rare' => 1]);
        $a5 = $this->pickMulti(['desktop', 'mobile', 'tablet', 'multi'], 1, 2);

        $a6 = random_int(1, 100) <= 18
            ? ['none']
            : $this->pickMulti(['moodle', 'classroom', 'coursera', 'udemy', 'teams', 'other_school', 'other'], 1, 3);

        // C1: nghiêng chọn "career" (hỗ trợ định hướng nghề / CV)
        $c1 = $this->pickC1();

        // D0: nghiêng AI Career (~80%+ có career)
        $d0 = $this->pickD0();

        $answers = [
            'A1' => $a1,
            'A2' => (string) $majorName,
            'A3' => $a3,
            'A4' => $a4,
            'A5' => $a5,
            'A6' => $a6,
            'B1' => $this->likert(),
            'B2' => $this->likert(),
            'B3' => $this->likert(),
            'B4' => $this->optionalText([
                'AI Career góp ý CV khá sát với vị trí mình muốn ứng tuyển.',
                'Gợi ý kỹ năng còn thiếu giúp mình biết cần học gì tiếp.',
                'Phần gợi ý nghề/CV gắn với mục tiêu của mình.',
                'Hệ thống gợi ý bài học theo tiến độ của tôi.',
                '',
                '',
            ]),
            'C1' => $c1,
            'C2' => $this->likert(),
            'C3' => $this->pickWeighted(['clear_yes' => 5, 'somewhat' => 4, 'same' => 2, 'worse' => 1]),
            'C4' => $this->optionalText([
                'Upload CV và nhận góp ý theo vị trí Backend / BA mình đang nhắm.',
                'AI Career chỉ ra gap kỹ năng so với JD quan tâm.',
                'Khi ôn thi, tóm tắt bài giúp mình nắm ý nhanh hơn.',
                'Gợi ý khoá bổ trợ khớp với điểm yếu trên bảng điểm.',
                '',
                '',
            ]),
            'D0' => $d0,
            'E1' => $this->likert(),
            'E2' => $this->likert(),
            'E3' => $this->likert(),
            'E4' => $this->nps(),
            'F1' => $this->optionalText([
                'Muốn AI Career hỗ trợ nhiều mẫu CV hơn theo ngành.',
                'Đôi khi phản hồi AI hơi chậm.',
                'Muốn cá nhân hoá rõ hơn trên trang chủ.',
                '',
                '',
            ]),
            'F2' => $this->optionalText([
                'Đánh giá CV chi tiết hơn theo JD cụ thể.',
                'Gợi ý lộ trình kỹ năng gắn với mục tiêu nghề rõ hơn.',
                'Gợi ý lịch học cá nhân theo môn đang yếu.',
                'Tóm tắt theo chương rõ hơn.',
                '',
            ]),
            'F3' => $this->optionalText(['', '', 'Demo AI Career hữu ích cho định hướng nghề.']),
        ];

        if (in_array('other', $a6, true)) {
            $answers['A6_other'] = 'Canvas / LMS nội bộ trường cũ';
        }
        if (in_array('other', $c1, true)) {
            $answers['C1_other'] = 'Nhắc nhở tiến độ học cá nhân';
        }

        $aiUsed = array_values(array_intersect($d0, ['chatbot', 'career', 'study']));
        if (in_array('chatbot', $aiUsed, true)) {
            $answers['D1_1'] = $this->likert();
            $answers['D1_2'] = $this->likert();
            $answers['D1_3'] = $this->likert();
            $answers['D1_4'] = $this->likert();
        }
        if (in_array('career', $aiUsed, true)) {
            // Likert AI Career hơi cao hơn các mục khác
            $answers['D2_1'] = $this->likertHigh();
            $answers['D2_2'] = $this->likertHigh();
            $answers['D2_3'] = $this->likertHigh();
            $answers['D2_4'] = $this->likertHigh();
        }
        if (in_array('study', $aiUsed, true)) {
            $answers['D3_1'] = $this->likert();
            $answers['D3_2'] = $this->likert();
            $answers['D3_3'] = $this->likert();
            $answers['D3_4'] = $this->likert();
        }
        if ($aiUsed) {
            // D4_1: tính năng hữu ích nhất — nghiêng career
            $weights = [];
            foreach ($aiUsed as $feat) {
                $weights[$feat] = $feat === 'career' ? 10 : 2;
            }
            $answers['D4_1'] = (string) $this->pickWeighted($weights);
            $answers['D4_2'] = $this->likert();
            $answers['D4_3'] = $this->likertHigh();
        }

        return $answers;
    }

    /** @return list<string> */
    private function pickC1(): array
    {
        $roll = random_int(1, 100);
        if ($roll <= 8) {
            return ['none'];
        }
        if ($roll <= 40) {
            return ['career'];
        }
        if ($roll <= 65) {
            return ['career', 'review'];
        }
        if ($roll <= 82) {
            return ['career', 'path', 'overview'];
        }
        if ($roll <= 92) {
            return array_values(array_unique(array_merge(['career'], $this->pickMulti(['content', 'path', 'review', 'overview'], 1, 2))));
        }

        return $this->pickMulti(['content', 'path', 'review', 'overview', 'other'], 1, 3);
    }

    /** @return list<string> */
    private function pickD0(): array
    {
        $roll = random_int(1, 100);
        // ~8% chưa dùng AI
        if ($roll <= 8) {
            return ['none'];
        }
        // ~32% chỉ Career
        if ($roll <= 40) {
            return ['career'];
        }
        // ~25% Career + Chatbot
        if ($roll <= 65) {
            return ['career', 'chatbot'];
        }
        // ~15% Career + Study
        if ($roll <= 80) {
            return ['career', 'study'];
        }
        // ~12% cả ba (có Career)
        if ($roll <= 92) {
            return ['career', 'chatbot', 'study'];
        }
        // ~8% không Career (chatbot/study)
        return $this->pickMulti(['chatbot', 'study'], 1, 2);
    }

    private function likert(): int
    {
        return (int) $this->pickWeighted([1 => 2, 2 => 5, 3 => 18, 4 => 40, 5 => 35]);
    }

    private function likertHigh(): int
    {
        return (int) $this->pickWeighted([1 => 1, 2 => 3, 3 => 12, 4 => 42, 5 => 42]);
    }

    private function nps(): int
    {
        return (int) $this->pickWeighted([
            0 => 1, 1 => 1, 2 => 1, 3 => 2, 4 => 3, 5 => 5,
            6 => 8, 7 => 14, 8 => 24, 9 => 24, 10 => 17,
        ]);
    }

    /**
     * @param  array<string|int, int>  $weights
     */
    private function pickWeighted(array $weights): string|int
    {
        $sum = array_sum($weights);
        $r = random_int(1, max(1, $sum));
        $acc = 0;
        foreach ($weights as $key => $w) {
            $acc += $w;
            if ($r <= $acc) {
                return $key;
            }
        }

        return array_key_first($weights);
    }

    /**
     * @param  list<string>  $pool
     * @return list<string>
     */
    private function pickMulti(array $pool, int $min, int $max): array
    {
        shuffle($pool);
        $n = random_int($min, min($max, count($pool)));

        return array_values(array_slice($pool, 0, $n));
    }

    /**
     * @param  list<string>  $options
     */
    private function optionalText(array $options): string
    {
        return $options[array_rand($options)];
    }
}
