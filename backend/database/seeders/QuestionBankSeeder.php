<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QuestionBankSeeder extends Seeder
{
    public function run(): void
    {
        $courses = Course::query()->orderBy('id')->get();

        if ($courses->isEmpty()) {
            $this->command?->warn('Chưa có khóa học nào. Chạy DatabaseSeeder trước.');
            return;
        }

        foreach ($courses as $course) {
            $this->command?->info("Seeding question bank cho khóa #{$course->id}: {$course->title}");
            $this->seedForCourse($course);
        }
    }

    private function seedForCourse(Course $course): void
    {
        $bank = QuestionBank::query()->updateOrCreate(
            ['course_id' => $course->id, 'name' => 'Ngân hàng câu hỏi tổng hợp'],
            ['description' => '<p>Bộ câu hỏi đa dạng, bao gồm trắc nghiệm, đúng/sai, tự luận, ghép đôi và câu hỏi số cho khóa <strong>' . e($course->title) . '</strong>.</p>'],
        );

        $groupA = QuestionGroup::query()->updateOrCreate(
            ['course_id' => $course->id, 'question_bank_id' => $bank->id, 'name' => 'Kiến thức cơ bản'],
            ['description' => 'Nhóm câu hỏi ôn tập kiến thức nền tảng.', 'sort_order' => 1],
        );

        $groupB = QuestionGroup::query()->updateOrCreate(
            ['course_id' => $course->id, 'question_bank_id' => $bank->id, 'name' => 'Vận dụng và nâng cao'],
            ['description' => 'Nhóm câu hỏi đánh giá khả năng vận dụng và tư duy bậc cao.', 'sort_order' => 2],
        );

        $blueprints = $this->questionBlueprints($course);

        foreach ($blueprints as $seed) {
            $group = ($seed['difficulty'] ?? 1) <= 2 ? $groupA : $groupB;

            $question = Question::query()->updateOrCreate(
                ['course_id' => $course->id, 'content' => $seed['content']],
                [
                    'question_bank_id'  => $bank->id,
                    'question_group_id' => $group->id,
                    'type'              => $seed['type'],
                    'difficulty'        => $seed['difficulty'] ?? 1,
                    'default_score'     => $seed['default_score'] ?? 1.00,
                    'explanation'       => $seed['explanation'] ?? null,
                    'feedback'          => $seed['feedback'] ?? null,
                    'general_feedback'  => $seed['general_feedback'] ?? null,
                ],
            );

            // Upsert answers — dùng updateOrInsert thay vì delete+insert
            // để tránh phá FK nếu quiz_attempts đang giữ tham chiếu vào answers
            foreach (($seed['answers'] ?? []) as $index => $answer) {
                DB::table('answers')->updateOrInsert(
                    [
                        'question_id' => $question->id,
                        'content'     => $answer['content'],
                    ],
                    [
                        'is_correct'  => $answer['is_correct'],
                        'sub_content' => $answer['sub_content'] ?? null,
                        'sort_order'  => $index + 1,
                        'order'       => $index + 1,
                        'updated_at'  => now(),
                        'created_at'  => now(),
                    ]
                );
            }
        }
    }

    private function questionBlueprints(Course $course): array
    {
        $topic = $course->title;

        return [
            // ── single_choice (5 câu) ──────────────────────────────────────
            [
                'type'       => 'single_choice',
                'content'    => "<p>Mục tiêu chính khi hoàn thành khóa <em>{$topic}</em> là gì?</p>",
                'difficulty' => 1,
                'default_score' => 1,
                'feedback'   => '<p>Hãy xem lại phần giới thiệu khóa học để nắm rõ mục tiêu.</p>',
                'explanation' => '<p>Mục tiêu cuối cùng luôn là áp dụng được kiến thức vào tình huống thực tế.</p>',
                'general_feedback' => '<p>Câu hỏi này đánh giá mức nhận biết về mục tiêu khóa học.</p>',
                'answers'    => [
                    ['content' => 'Hiểu và áp dụng kiến thức vào tình huống thực tế', 'is_correct' => true],
                    ['content' => 'Chỉ ghi nhớ tên các chương', 'is_correct' => false],
                    ['content' => 'Hoàn thành nhanh nhất có thể', 'is_correct' => false],
                    ['content' => 'Bỏ qua bài tập và chỉ xem video', 'is_correct' => false],
                ],
            ],
            [
                'type'       => 'single_choice',
                'content'    => '<p>Trong quy trình học tập, bước nào giúp kiến thức được ghi nhớ lâu nhất?</p>',
                'difficulty' => 2,
                'default_score' => 1,
                'feedback'   => '<p>Thực hành chủ động luôn hiệu quả hơn tiếp nhận thụ động.</p>',
                'explanation' => '<p>Nghiên cứu cho thấy việc áp dụng và giảng lại giúp ghi nhớ sâu nhất (learning pyramid).</p>',
                'general_feedback' => '<p>Đây là câu hỏi kiểm tra hiểu biết về phương pháp học.</p>',
                'answers'    => [
                    ['content' => 'Xem video bài giảng nhiều lần', 'is_correct' => false],
                    ['content' => 'Áp dụng vào dự án thực tế hoặc dạy lại cho người khác', 'is_correct' => true],
                    ['content' => 'Ghi chép bài giảng ra giấy', 'is_correct' => false],
                    ['content' => 'Đọc lại slide', 'is_correct' => false],
                ],
            ],
            [
                'type'       => 'single_choice',
                'content'    => '<p>Khi gặp khó khăn trong bài học, phương pháp giải quyết tốt nhất là gì?</p>',
                'difficulty' => 2,
                'default_score' => 1,
                'feedback'   => '<p>Sử dụng tài nguyên hỗ trợ sẵn có trong hệ thống.</p>',
                'explanation' => '<p>Phần hỏi đáp và tài liệu tham khảo là công cụ hữu ích nhất khi cần hỗ trợ.</p>',
                'general_feedback' => '<p>Câu hỏi kiểm tra kỹ năng tự giải quyết vấn đề.</p>',
                'answers'    => [
                    ['content' => 'Bỏ qua và chuyển sang bài khác', 'is_correct' => false],
                    ['content' => 'Đợi cuối khóa mới hỏi giảng viên', 'is_correct' => false],
                    ['content' => 'Đặt câu hỏi trong phần Q&A và xem lại tài liệu liên quan', 'is_correct' => true],
                    ['content' => 'Ngưng học luôn', 'is_correct' => false],
                ],
            ],
            [
                'type'       => 'single_choice',
                'content'    => '<p>Vai trò của <strong>ngân hàng câu hỏi</strong> trong hệ thống kiểm tra là gì?</p>',
                'difficulty' => 3,
                'default_score' => 1.5,
                'feedback'   => '<p>Question bank là thành phần cốt lõi cho mọi bài kiểm tra tự động.</p>',
                'explanation' => '<p>Ngân hàng câu hỏi chuẩn hóa nội dung, cho phép tái sử dụng và random đề thi.</p>',
                'general_feedback' => '<p>Câu hỏi ở mức vận dụng — cần hiểu kiến trúc hệ thống quiz.</p>',
                'answers'    => [
                    ['content' => 'Chỉ lưu trữ câu hỏi duy nhất cho 1 quiz', 'is_correct' => false],
                    ['content' => 'Chuẩn hóa câu hỏi, tái sử dụng và tạo đề ngẫu nhiên', 'is_correct' => true],
                    ['content' => 'Trang trí giao diện quản trị', 'is_correct' => false],
                    ['content' => 'Không liên quan đến quiz', 'is_correct' => false],
                ],
            ],
            [
                'type'       => 'single_choice',
                'content'    => '<p>Khi thiết kế một bài kiểm tra chất lượng, yếu tố nào cần ưu tiên nhất?</p>',
                'difficulty' => 4,
                'default_score' => 2,
                'feedback'   => '<p>Thiết kế bài kiểm tra tốt cần cân đối giữa nội dung và hình thức.</p>',
                'explanation' => '<p>Câu hỏi tốt cần đáp án duy nhất đúng, phương án nhiễu hợp lý, và giải thích rõ ràng.</p>',
                'general_feedback' => '<p>Câu hỏi đánh giá kỹ năng thiết kế đề thi — mức vận dụng cao.</p>',
                'answers'    => [
                    ['content' => 'Đáp án duy nhất đúng, phương án nhiễu hợp lý và có giải thích', 'is_correct' => true],
                    ['content' => 'Nhiều đáp án đúng để tăng độ khó', 'is_correct' => false],
                    ['content' => 'Câu hỏi càng dài càng tốt', 'is_correct' => false],
                    ['content' => 'Không cần feedback hay giải thích', 'is_correct' => false],
                ],
            ],

            // ── multiple_choice (3 câu) ────────────────────────────────────
            [
                'type'       => 'multiple_choice',
                'content'    => '<p>Những thành phần nào sau đây thuộc hệ thống đánh giá học tập? <em>(Chọn tất cả đáp án đúng)</em></p>',
                'difficulty' => 2,
                'default_score' => 1.5,
                'feedback'   => '<p>Hệ thống đánh giá bao gồm nhiều hình thức khác nhau.</p>',
                'explanation' => '<p>Quiz, assignment và review đều là công cụ đánh giá. Thanh toán thuộc hệ thống thương mại.</p>',
                'general_feedback' => '<p>Câu hỏi dạng chọn nhiều đáp án — mức thông hiểu.</p>',
                'answers'    => [
                    ['content' => 'Quiz trắc nghiệm', 'is_correct' => true],
                    ['content' => 'Assignment nộp bài', 'is_correct' => true],
                    ['content' => 'Review đánh giá khóa học', 'is_correct' => true],
                    ['content' => 'Thanh toán đơn hàng', 'is_correct' => false],
                ],
            ],
            [
                'type'       => 'multiple_choice',
                'content'    => '<p>Để học tập hiệu quả, học viên nên thực hiện những bước nào? <em>(Chọn tất cả đáp án đúng)</em></p>',
                'difficulty' => 3,
                'default_score' => 2,
                'feedback'   => '<p>Kết hợp nhiều phương pháp học sẽ đạt hiệu quả tốt nhất.</p>',
                'explanation' => '<p>Đặt mục tiêu, thực hành đều đặn và tham gia trao đổi là 3 trụ cột của học tập hiệu quả.</p>',
                'general_feedback' => '<p>Câu hỏi kiểm tra phương pháp học tập — chọn nhiều đáp án.</p>',
                'answers'    => [
                    ['content' => 'Đặt mục tiêu rõ ràng cho từng tuần', 'is_correct' => true],
                    ['content' => 'Thực hành sau mỗi bài học', 'is_correct' => true],
                    ['content' => 'Tham gia phần hỏi đáp để trao đổi', 'is_correct' => true],
                    ['content' => 'Chỉ xem video mà không làm bài tập', 'is_correct' => false],
                    ['content' => 'Bỏ qua tất cả quiz kiểm tra', 'is_correct' => false],
                ],
            ],
            [
                'type'       => 'multiple_choice',
                'content'    => '<p>Những loại học liệu nào sau đây yêu cầu học viên <strong>chủ động tương tác</strong>? <em>(Chọn tất cả đáp án đúng)</em></p>',
                'difficulty' => 3,
                'default_score' => 2,
                'feedback'   => '<p>Phân biệt giữa học liệu chủ động và thụ động.</p>',
                'explanation' => '<p>Quiz, assignment và live workshop yêu cầu học viên chủ động tham gia, khác với video là tiếp nhận thụ động.</p>',
                'general_feedback' => '<p>Câu hỏi phân loại hoạt động học tập.</p>',
                'answers'    => [
                    ['content' => 'Quiz kiểm tra', 'is_correct' => true],
                    ['content' => 'Bài tập nộp file', 'is_correct' => true],
                    ['content' => 'Live workshop', 'is_correct' => true],
                    ['content' => 'Xem video bài giảng', 'is_correct' => false],
                ],
            ],

            // ── true_false (3 câu) ─────────────────────────────────────────
            [
                'type'       => 'true_false',
                'content'    => '<p>Học viên có thể xem lại bài học đã hoàn thành bất cứ lúc nào.</p>',
                'difficulty' => 1,
                'default_score' => 0.5,
                'feedback'   => '<p>Hệ thống cho phép xem lại không giới hạn sau khi ghi danh.</p>',
                'explanation' => '<p>Đúng — tất cả bài học đã mở đều có thể xem lại bất kỳ lúc nào.</p>',
                'general_feedback' => '<p>Câu hỏi kiểm tra hiểu biết về quyền truy cập nội dung.</p>',
                'answers'    => [
                    ['content' => 'Đúng', 'is_correct' => true],
                    ['content' => 'Sai', 'is_correct' => false],
                ],
            ],
            [
                'type'       => 'true_false',
                'content'    => '<p>Quiz chỉ có thể tạo từ câu hỏi dạng trắc nghiệm một đáp án.</p>',
                'difficulty' => 2,
                'default_score' => 0.5,
                'feedback'   => '<p>Hệ thống hỗ trợ nhiều loại câu hỏi khác nhau.</p>',
                'explanation' => '<p>Sai — quiz hỗ trợ: trắc nghiệm 1 đáp án, nhiều đáp án, đúng/sai, tự luận, ghép đôi, sắp xếp, số và trả lời ngắn.</p>',
                'general_feedback' => '<p>Câu hỏi kiểm tra hiểu biết về tính năng hệ thống quiz.</p>',
                'answers'    => [
                    ['content' => 'Đúng', 'is_correct' => false],
                    ['content' => 'Sai', 'is_correct' => true],
                ],
            ],
            [
                'type'       => 'true_false',
                'content'    => '<p>Giảng viên có thể sử dụng lại câu hỏi từ ngân hàng câu hỏi cho nhiều bài kiểm tra khác nhau.</p>',
                'difficulty' => 1,
                'default_score' => 0.5,
                'feedback'   => '<p>Tái sử dụng câu hỏi là lợi thế lớn nhất của question bank.</p>',
                'explanation' => '<p>Đúng — đây chính là mục đích thiết kế của ngân hàng câu hỏi.</p>',
                'general_feedback' => '<p>Câu hỏi đúng/sai cơ bản về tính năng question bank.</p>',
                'answers'    => [
                    ['content' => 'Đúng', 'is_correct' => true],
                    ['content' => 'Sai', 'is_correct' => false],
                ],
            ],

            // ── short_answer (2 câu) ───────────────────────────────────────
            [
                'type'       => 'short_answer',
                'content'    => '<p>Trong hệ thống LMS, tên viết tắt của <em>Learning Management System</em> gồm mấy ký tự?</p>',
                'difficulty' => 1,
                'default_score' => 1,
                'feedback'   => '<p>LMS là viết tắt phổ biến trong giáo dục số.</p>',
                'explanation' => '<p>LMS gồm 3 ký tự: L-M-S.</p>',
                'general_feedback' => '<p>Câu hỏi trả lời ngắn — kiểm tra kiến thức thuật ngữ.</p>',
                'answers'    => [
                    ['content' => '3', 'is_correct' => true],
                ],
            ],
            [
                'type'       => 'short_answer',
                'content'    => '<p>Viết tên đầy đủ bằng tiếng Anh của hình thức kiểm tra trực tuyến thường dùng trong LMS (viết tắt: <strong>quiz</strong>).</p>',
                'difficulty' => 2,
                'default_score' => 1,
                'feedback'   => '<p>Quiz là hình thức đánh giá nhanh phổ biến.</p>',
                'explanation' => '<p>Quiz (bài kiểm tra nhanh) — đáp án chấp nhận: quiz, Quiz.</p>',
                'general_feedback' => '<p>Câu hỏi dạng trả lời ngắn.</p>',
                'answers'    => [
                    ['content' => 'quiz', 'is_correct' => true],
                ],
            ],

            // ── numerical (2 câu) ──────────────────────────────────────────
            [
                'type'       => 'numerical',
                'content'    => '<p>Một khóa học có 3 chương, mỗi chương có 4 bài học. Hỏi tổng cộng có bao nhiêu bài học?</p>',
                'difficulty' => 1,
                'default_score' => 1,
                'feedback'   => '<p>Phép nhân cơ bản: 3 × 4 = 12.</p>',
                'explanation' => '<p>3 chương × 4 bài/chương = 12 bài học.</p>',
                'general_feedback' => '<p>Câu hỏi dạng số — mức nhận biết.</p>',
                'answers'    => [
                    ['content' => '12', 'is_correct' => true],
                ],
            ],
            [
                'type'       => 'numerical',
                'content'    => '<p>Nếu điểm đạt của quiz là 70% và tổng điểm là 100, học viên cần đạt ít nhất bao nhiêu điểm để pass?</p>',
                'difficulty' => 2,
                'default_score' => 1,
                'feedback'   => '<p>70% của 100 điểm = 70 điểm.</p>',
                'explanation' => '<p>Điểm đạt = 70% × 100 = 70 điểm.</p>',
                'general_feedback' => '<p>Câu hỏi tính toán đơn giản.</p>',
                'answers'    => [
                    ['content' => '70', 'is_correct' => true],
                ],
            ],

            // ── essay (2 câu) ──────────────────────────────────────────────
            [
                'type'       => 'essay',
                'content'    => "<p>Hãy trình bày kế hoạch học tập của bạn cho khóa <strong>{$topic}</strong> trong 4 tuần tới. Bao gồm: mục tiêu, lịch học, và cách theo dõi tiến độ.</p>",
                'difficulty' => 4,
                'default_score' => 5,
                'feedback'   => '<p>Một kế hoạch tốt cần cụ thể, đo lường được và có deadline rõ ràng.</p>',
                'explanation' => '<p>Câu hỏi mở — giảng viên sẽ chấm thủ công dựa trên tính chi tiết và khả thi của kế hoạch.</p>',
                'general_feedback' => '<p>Câu hỏi tự luận yêu cầu tư duy tổng hợp và lập kế hoạch.</p>',
                'answers'    => [],
            ],
            [
                'type'       => 'essay',
                'content'    => '<p>Phân tích 3 thách thức lớn nhất khi triển khai hệ thống học trực tuyến tại Việt Nam và đề xuất giải pháp cho mỗi thách thức.</p>',
                'difficulty' => 5,
                'default_score' => 5,
                'feedback'   => '<p>Cần phân tích thực tế và có giải pháp cụ thể, không chung chung.</p>',
                'explanation' => '<p>Câu hỏi sáng tạo — đánh giá khả năng phân tích vấn đề và đề xuất giải pháp.</p>',
                'general_feedback' => '<p>Câu hỏi mức sáng tạo — không có đáp án duy nhất đúng.</p>',
                'answers'    => [],
            ],

            // ── matching (2 câu) ───────────────────────────────────────────
            [
                'type'       => 'matching',
                'content'    => '<p>Ghép đôi các thuật ngữ với định nghĩa tương ứng:</p>',
                'difficulty' => 3,
                'default_score' => 2,
                'feedback'   => '<p>Xem lại phần thuật ngữ cơ bản của hệ thống LMS.</p>',
                'explanation' => '<p>Question Bank → Kho câu hỏi tái sử dụng; Quiz → Bài kiểm tra nhanh; Assignment → Bài tập nộp bài; Enrollment → Ghi danh khóa học.</p>',
                'general_feedback' => '<p>Câu hỏi dạng ghép đôi — mức vận dụng.</p>',
                'answers'    => [
                    ['content' => 'Question Bank', 'is_correct' => true, 'sub_content' => 'Kho câu hỏi tái sử dụng'],
                    ['content' => 'Quiz', 'is_correct' => true, 'sub_content' => 'Bài kiểm tra nhanh'],
                    ['content' => 'Assignment', 'is_correct' => true, 'sub_content' => 'Bài tập nộp bài'],
                    ['content' => 'Enrollment', 'is_correct' => true, 'sub_content' => 'Ghi danh khóa học'],
                ],
            ],
            [
                'type'       => 'matching',
                'content'    => '<p>Ghép mỗi mức độ nhận thức Bloom với mô tả phù hợp:</p>',
                'difficulty' => 4,
                'default_score' => 2.5,
                'feedback'   => '<p>Tham khảo thang Bloom taxonomy để hiểu rõ hơn.</p>',
                'explanation' => '<p>Nhận biết → Nhớ lại thông tin; Thông hiểu → Giải thích ý nghĩa; Vận dụng → Sử dụng trong tình huống mới; Phân tích → Chia nhỏ và tìm mối quan hệ.</p>',
                'general_feedback' => '<p>Câu hỏi kiểm tra kiến thức về phân loại mức độ tư duy.</p>',
                'answers'    => [
                    ['content' => 'Nhận biết', 'is_correct' => true, 'sub_content' => 'Nhớ lại thông tin đã học'],
                    ['content' => 'Thông hiểu', 'is_correct' => true, 'sub_content' => 'Giải thích được ý nghĩa'],
                    ['content' => 'Vận dụng', 'is_correct' => true, 'sub_content' => 'Sử dụng trong tình huống mới'],
                    ['content' => 'Phân tích', 'is_correct' => true, 'sub_content' => 'Chia nhỏ và tìm mối quan hệ'],
                ],
            ],
        ];
    }
}
