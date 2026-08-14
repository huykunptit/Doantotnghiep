<?php

namespace Database\Seeders;

use App\Models\AdministrativeClass;
use App\Models\Term;
use App\Models\Tuition;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Học phí: 1 dòng / sinh viên / kỳ có trong khung CTĐT (termMap) — quá khứ,
 * hiện tại và tương lai — để bảng học phí có đủ toàn bộ lịch sử + kế hoạch đóng.
 *
 * Chạy riêng (không phải seed lại toàn bộ dữ liệu demo):
 *   php artisan db:seed --class=Database\\Seeders\\TuitionSeeder
 *
 * Yêu cầu: đã có AdministrativeClassTerm (termMap) — do AcademicDemoDataSeeder tạo.
 */
class TuitionSeeder extends Seeder
{
    /** Học phí cố định theo chương trình (VND/kỳ), tăng nhẹ theo từng kỳ. */
    private const TUITION_BY_PROGRAM = [
        'CNTT' => 9_500_000,
        'DTVT' => 9_000_000,
        'QTKD' => 8_500_000,
    ];

    public function run(): void
    {
        $currentTerm = Term::operational();
        if (! $currentTerm) {
            $this->command?->warn('TuitionSeeder: chưa có Term nào, bỏ qua.');
            return;
        }

        $classes = AdministrativeClass::query()
            ->with(['program:id,code', 'termMap.term'])
            ->whereNotNull('cohort_id')
            ->whereNotNull('curriculum_id')
            ->get();

        $count = 0;

        foreach ($classes as $class) {
            $students = User::query()
                ->where('user_type', 'student')
                ->where('administrative_class_id', $class->id)
                ->get();

            if ($students->isEmpty() || $class->termMap->isEmpty()) {
                continue;
            }

            $programCode = $class->program?->code ?? 'CNTT';
            $baseAmount = self::TUITION_BY_PROGRAM[$programCode] ?? 8_500_000;

            foreach ($class->termMap as $map) {
                $term = $map->term;
                if (! $term) {
                    continue;
                }

                $isHistorical = $term->end_date?->lt($currentTerm->start_date) ?? false;
                $isCurrent = (int) $term->id === (int) $currentTerm->id;
                // Học phí tăng nhẹ theo từng kỳ (trượt giá theo năm học).
                $amount = $baseAmount + max(0, ((int) $map->term_number - 1)) * 150_000;

                foreach ($students as $student) {
                    $status = $isHistorical ? 'paid' : 'unpaid';
                    if ($isHistorical && $student->id % 9 === 0) {
                        $status = 'unpaid'; // vài SV còn nợ học phí kỳ cũ
                    } elseif ($isCurrent && $student->id % 3 === 0) {
                        $status = 'paid'; // vài SV đã đóng học phí kỳ hiện tại sớm
                    }

                    Tuition::query()->updateOrCreate(
                        ['user_id' => $student->id, 'term_id' => $term->id],
                        [
                            'amount' => $amount,
                            'status' => $status,
                            'paid_at' => $status === 'paid'
                                ? ($term->end_date?->copy()->subDays(20) ?? now()->subDays(20))
                                : null,
                            'note' => 'Học phí ' . $term->displayName(),
                        ]
                    );
                    $count++;
                }
            }
        }

        $this->command?->info("TuitionSeeder: {$count} học phí upserted.");
    }
}
