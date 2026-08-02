<?php

namespace App\Support;

/**
 * Khảo sát trải nghiệm LMS cá nhân hoá (đồ án) — form v3.
 * Tập trung cảm nhận cá nhân hoá trên bản demo; tránh câu khẳng định chất lượng học liệu/LMS “tốt hơn”.
 */
class ExperienceSurveyDefinition
{
    public const VERSION = 'v3';

    public static function meta(): array
    {
        return [
            'version' => self::VERSION,
            'title' => 'Khảo sát trải nghiệm sinh viên với hệ thống LMS cá nhân hoá học tập',
            'intro' => 'Khảo sát phục vụ đồ án tốt nghiệp, nhằm tìm hiểu cảm nhận của bạn về tính cá nhân hoá học tập khi được tích hợp vào LMS (đặc biệt các hỗ trợ AI). Hệ thống đang ở giai đoạn demo thử nghiệm — vui lòng đánh giá dựa trên trải nghiệm thực tế của bạn, không cần so sánh chất lượng học liệu với hệ thống khác. Dữ liệu chỉ dùng cho nghiên cứu và được bảo mật. Thời gian: khoảng 5–8 phút. Bạn có thể gửi lại để cập nhật đánh giá.',
            'likert_labels' => [
                1 => 'Hoàn toàn không đồng ý',
                2 => 'Không đồng ý',
                3 => 'Trung lập',
                4 => 'Đồng ý',
                5 => 'Hoàn toàn đồng ý',
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function sections(): array
    {
        return [
            [
                'id' => 'A',
                'title' => 'Phần A — Thông tin chung',
                'questions' => [
                    self::single('A1', 'Bạn là sinh viên năm mấy?', [
                        'year1' => 'Năm 1',
                        'year2' => 'Năm 2',
                        'year3' => 'Năm 3',
                        'year4' => 'Năm 4',
                        'other' => 'Khác',
                    ]),
                    self::text('A2', 'Ngành học của bạn là gì?'),
                    self::single('A3', 'Bạn đã trải nghiệm hệ thống demo trong bao lâu?', [
                        'lt1w' => '< 1 tuần',
                        '1_4w' => '1–4 tuần',
                        '1_3m' => '1–3 tháng',
                        'gt3m' => '> 3 tháng',
                    ]),
                    self::single('A4', 'Tần suất bạn sử dụng hệ thống trong thời gian trải nghiệm?', [
                        'daily' => 'Hàng ngày',
                        'weekly' => 'Vài lần/tuần',
                        'monthly' => 'Vài lần/tháng',
                        'rare' => 'Hiếm khi',
                    ]),
                    self::multi('A5', 'Thiết bị bạn thường dùng để truy cập?', [
                        'desktop' => 'Máy tính',
                        'mobile' => 'Điện thoại',
                        'tablet' => 'Máy tính bảng',
                        'multi' => 'Nhiều thiết bị',
                    ]),
                    self::multi('A6', 'Bạn đã từng dùng hệ thống LMS/học trực tuyến nào khác? (chỉ để tham chiếu nền tảng)', [
                        'moodle' => 'Moodle',
                        'classroom' => 'Google Classroom',
                        'coursera' => 'Coursera',
                        'udemy' => 'Udemy',
                        'teams' => 'MS Teams',
                        'other_school' => 'Hệ thống của trường khác',
                        'other' => 'Khác',
                        'none' => 'Chưa từng dùng hệ thống LMS nào khác',
                    ]),
                    self::text('A6_other', 'Nếu chọn Khác, vui lòng ghi rõ:', required: false),
                ],
            ],
            [
                'id' => 'B',
                'title' => 'Phần B — Cảm nhận về cá nhân hoá trên LMS',
                'scale' => 'likert5',
                'hint' => 'Các câu dưới đây hỏi về mức độ bạn cảm thấy hệ thống “hiểu / phù hợp với bạn”, không đánh giá học liệu tốt hay kém hơn hệ thống khác.',
                'questions' => [
                    self::likert('B1', 'Tôi cảm nhận được hệ thống có yếu tố cá nhân hoá (không chỉ hiển thị nội dung giống nhau cho mọi người).'),
                    self::likert('B2', 'Các gợi ý/hỗ trợ trên hệ thống có liên quan đến tình trạng học tập hoặc mục tiêu của tôi.'),
                    self::likert('B3', 'So với LMS tôi từng dùng (nếu có), điểm khác biệt rõ nhất tôi nhận ra ở đây là khả năng hỗ trợ theo cá nhân.'),
                    self::textarea('B4', 'Bạn mô tả ngắn trải nghiệm cá nhân hoá mà bạn nhận thấy (có thể để trống nếu chưa rõ):', required: false),
                ],
            ],
            [
                'id' => 'C',
                'title' => 'Phần C — Cá nhân hoá hỗ trợ được khía cạnh nào?',
                'questions' => [
                    self::multi('C1', 'Trong thời gian dùng demo, bạn thấy cá nhân hoá hữu ích ở những việc nào? (chọn nhiều)', [
                        'content' => 'Gợi ý nội dung/bài học gần với trình độ hoặc nhu cầu của tôi',
                        'path' => 'Gợi ý bước học tiếp theo theo tiến độ của tôi',
                        'review' => 'Hỗ trợ ôn tập / tóm tắt / giải đáp khi tôi cần',
                        'career' => 'Hỗ trợ định hướng nghề / xem xét CV theo mục tiêu của tôi',
                        'overview' => 'Cho tôi cái nhìn tổng quan về kết quả học và hướng củng cố',
                        'none' => 'Chưa cảm nhận được hỗ trợ cá nhân hoá rõ rệt',
                        'other' => 'Khác',
                    ]),
                    self::text('C1_other', 'Nếu chọn Khác, vui lòng ghi rõ:', required: false),
                    self::likert('C2', 'Nhìn chung, tính cá nhân hoá giúp ích cho quá trình học/trải nghiệm của tôi.'),
                    self::single('C3', 'Với trải nghiệm demo này, cá nhân hoá có giúp bạn định hướng việc học nhanh hơn không?', [
                        'clear_yes' => 'Có, rõ rệt',
                        'somewhat' => 'Có một chút',
                        'same' => 'Chưa thấy khác biệt',
                        'worse' => 'Làm trải nghiệm rối hơn',
                    ]),
                    self::textarea('C4', 'Nếu có, hãy nêu một tình huống cụ thể mà cá nhân hoá đã giúp bạn (có thể để trống):', required: false),
                ],
            ],
            [
                'id' => 'D0',
                'title' => 'Phần D — Trải nghiệm với các tính năng AI',
                'questions' => [
                    self::multi('D0', 'Bạn đã thử những tính năng AI nào trên hệ thống demo?', [
                        'chatbot' => 'AI Chatbot (hỏi đáp, tóm tắt, ôn tập)',
                        'career' => 'AI Career (xem xét CV, gợi ý theo mục tiêu nghề)',
                        'study' => 'AI Quản lý học tập (tổng quan hành trình, gợi ý củng cố)',
                        'none' => 'Chưa dùng tính năng AI nào',
                    ]),
                ],
            ],
            [
                'id' => 'D1',
                'title' => 'Phần D1 — AI Chatbot',
                'scale' => 'likert5',
                'show_if_ai' => 'chatbot',
                'hint' => 'Chỉ trả lời nếu bạn đã dùng AI Chatbot. Đánh giá mức hữu ích với bạn, không cần so với chatbot khác.',
                'questions' => [
                    self::likert('D1_1', 'Câu trả lời của chatbot nhìn chung hữu ích với thắc mắc của tôi.'),
                    self::likert('D1_2', 'Tóm tắt/hỗ trợ ôn tập giúp tôi nắm ý chính nhanh hơn trong ngữ cảnh bài đang học.'),
                    self::likert('D1_3', 'Chatbot giúp tôi biết cần hỏi/ôn gì tiếp theo thay vì tìm kiếm mù quáng.'),
                    self::likert('D1_4', 'Tốc độ phản hồi đủ chấp nhận được khi tôi đang học.'),
                ],
            ],
            [
                'id' => 'D2',
                'title' => 'Phần D2 — AI Career',
                'scale' => 'likert5',
                'show_if_ai' => 'career',
                'hint' => 'Chỉ trả lời nếu bạn đã dùng AI Career. Tập trung mức phù hợp với mục tiêu cá nhân của bạn.',
                'questions' => [
                    self::likert('D2_1', 'Phản hồi về CV giúp tôi nhìn ra điểm cần chỉnh theo hướng nghề tôi quan tâm.'),
                    self::likert('D2_2', 'Gợi ý chỉnh sửa/định hướng có liên quan tới vị trí hoặc lĩnh vực tôi muốn hướng tới.'),
                    self::likert('D2_3', 'Tôi hiểu rõ hơn mình còn thiếu kỹ năng/kinh nghiệm gì so với mục tiêu nghề.'),
                    self::likert('D2_4', 'Tính năng này giúp tôi định hướng bước tiếp theo rõ hơn (học gì / chuẩn bị gì).'),
                ],
            ],
            [
                'id' => 'D3',
                'title' => 'Phần D3 — AI Quản lý học tập',
                'scale' => 'likert5',
                'show_if_ai' => 'study',
                'hint' => 'Chỉ trả lời nếu bạn đã dùng AI Quản lý học tập / cố vấn học tập.',
                'questions' => [
                    self::likert('D3_1', 'Phần tổng quan giúp tôi hình dung tình trạng học tập của bản thân rõ hơn.'),
                    self::likert('D3_2', 'Các gợi ý củng cố/khoá bổ trợ có liên quan tới điểm yếu hoặc nhu cầu của tôi.'),
                    self::likert('D3_3', 'Tôi biết rõ hơn mình nên ưu tiên cải thiện điều gì tiếp theo.'),
                    self::likert('D3_4', 'Tôi sẵn sàng tham khảo các gợi ý này như một nguồn hỗ trợ (không bắt buộc phải “chính xác tuyệt đối”).'),
                ],
            ],
            [
                'id' => 'D4',
                'title' => 'Phần D4 — Nhìn nhận chung về AI trên LMS',
                'show_if_any_ai' => true,
                'questions' => [
                    self::single('D4_1', 'Trong các tính năng AI bạn đã thử, tính năng nào hữu ích nhất với bạn?', [
                        'chatbot' => 'AI Chatbot',
                        'career' => 'AI Career',
                        'study' => 'AI Quản lý học tập',
                    ]),
                    self::likert('D4_2', 'Tôi chấp nhận dùng AI trên LMS nếu dữ liệu học tập/CV được xử lý phục vụ hỗ trợ học tập của tôi.'),
                    self::likert('D4_3', 'AI trên hệ thống này chủ yếu giúp cá nhân hoá trải nghiệm học, hơn là chỉ thêm “tính năng cho có”.'),
                ],
            ],
            [
                'id' => 'E',
                'title' => 'Phần E — Mức độ hài lòng với trải nghiệm demo',
                'questions' => [
                    self::likert('E1', 'Nhìn chung, tôi hài lòng với trải nghiệm dùng bản demo này.'),
                    self::likert('E2', 'Nếu hệ thống được hoàn thiện và triển khai chính thức, tôi sẵn sàng tiếp tục sử dụng.'),
                    self::likert('E3', 'Tôi thấy việc tích hợp cá nhân hoá (kèm AI hỗ trợ) vào LMS là hướng đáng phát triển.'),
                    self::nps('E4', 'Bạn sẽ giới thiệu trải nghiệm demo này cho bạn học khác ở mức nào? (0–10)'),
                ],
            ],
            [
                'id' => 'F',
                'title' => 'Phần F — Góp ý để hoàn thiện cá nhân hoá',
                'questions' => [
                    self::textarea('F1', 'Khó khăn/bất tiện nào làm giảm trải nghiệm cá nhân hoá hoặc AI? (có thể để trống)', required: false),
                    self::textarea('F2', 'Bạn muốn hệ thống cá nhân hoá/AI bổ sung hoặc cải thiện điều gì?', required: false),
                    self::textarea('F3', 'Góp ý khác (nếu có):', required: false),
                ],
            ],
        ];
    }

    public static function definition(): array
    {
        return [
            ...self::meta(),
            'sections' => self::sections(),
        ];
    }

    public static function questionMap(): array
    {
        $map = [];
        foreach (self::sections() as $section) {
            foreach ($section['questions'] as $q) {
                $map[$q['id']] = $q + [
                    'section_id' => $section['id'],
                    'show_if_ai' => $section['show_if_ai'] ?? null,
                    'show_if_any_ai' => $section['show_if_any_ai'] ?? false,
                ];
            }
        }

        return $map;
    }

    public static function exportHeaders(): array
    {
        $headers = [
            'response_id',
            'user_id',
            'student_code',
            'student_name',
            'student_email',
            'submitted_at',
            'updated_at',
            'survey_version',
        ];
        foreach (array_keys(self::questionMap()) as $qid) {
            $headers[] = $qid;
        }

        return $headers;
    }

    private static function single(string $id, string $label, array $options, bool $required = true): array
    {
        return [
            'id' => $id,
            'type' => 'single',
            'label' => $label,
            'required' => $required,
            'options' => self::opts($options),
        ];
    }

    private static function multi(string $id, string $label, array $options, bool $required = true): array
    {
        return [
            'id' => $id,
            'type' => 'multi',
            'label' => $label,
            'required' => $required,
            'options' => self::opts($options),
        ];
    }

    private static function likert(string $id, string $label, bool $required = true): array
    {
        return [
            'id' => $id,
            'type' => 'likert5',
            'label' => $label,
            'required' => $required,
            'min' => 1,
            'max' => 5,
            'scale' => 'agree',
        ];
    }

    private static function nps(string $id, string $label, bool $required = true): array
    {
        return [
            'id' => $id,
            'type' => 'nps',
            'label' => $label,
            'required' => $required,
            'min' => 0,
            'max' => 10,
        ];
    }

    private static function text(string $id, string $label, bool $required = true): array
    {
        return [
            'id' => $id,
            'type' => 'text',
            'label' => $label,
            'required' => $required,
        ];
    }

    private static function textarea(string $id, string $label, bool $required = true): array
    {
        return [
            'id' => $id,
            'type' => 'textarea',
            'label' => $label,
            'required' => $required,
        ];
    }

    private static function opts(array $options): array
    {
        $out = [];
        foreach ($options as $value => $label) {
            $out[] = ['value' => (string) $value, 'label' => $label];
        }

        return $out;
    }
}
