<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\ExperienceSurveyResponse;
use App\Support\ExperienceSurveyDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExperienceSurveyController extends Controller
{
    public function definition(Request $request): JsonResponse
    {
        $user = $request->user();
        $existing = ExperienceSurveyResponse::where('user_id', $user->id)->first();
        $user->loadMissing('major:id,name,code');

        return response()->json([
            ...ExperienceSurveyDefinition::definition(),
            'submitted' => (bool) $existing,
            'submitted_at' => $existing?->submitted_at?->toIso8601String(),
            'updated_at' => $existing?->updated_at?->toIso8601String(),
            'answers' => $existing?->answers,
            'prefill' => [
                'A2' => $user->major?->name,
            ],
        ]);
    }

    public function submit(Request $request): JsonResponse
    {
        $user = $request->user();
        $request->validate(['answers' => 'required|array']);

        $answers = $request->input('answers', []);
        $errors = $this->validateAnswers($answers);
        if ($errors) {
            return response()->json([
                'message' => 'Vui lòng hoàn thành các câu hỏi bắt buộc.',
                'errors' => $errors,
            ], 422);
        }

        $normalized = $this->normalizeAnswers($answers);
        $existing = ExperienceSurveyResponse::where('user_id', $user->id)->first();

        if ($existing) {
            $existing->update([
                'survey_version' => ExperienceSurveyDefinition::VERSION,
                'answers' => $normalized,
                'submitted_at' => now(),
            ]);
            $row = $existing->fresh();
            $message = 'Đã cập nhật đánh giá khảo sát.';
        } else {
            $row = ExperienceSurveyResponse::create([
                'user_id' => $user->id,
                'survey_version' => ExperienceSurveyDefinition::VERSION,
                'answers' => $normalized,
                'submitted_at' => now(),
            ]);
            $message = 'Cảm ơn bạn đã hoàn thành khảo sát.';
        }

        return response()->json([
            'message' => $message,
            'submitted_at' => $row->submitted_at->toIso8601String(),
            'updated' => (bool) $existing,
        ], $existing ? 200 : 201);
    }

    public function adminSummary(Request $request): JsonResponse
    {
        $rows = ExperienceSurveyResponse::query()
            ->with(['user:id,name,email,student_code'])
            ->orderByDesc('submitted_at')
            ->get();

        $total = $rows->count();
        $numericIds = $this->numericQuestionIds(['likert5', 'nps']);

        $means = [];
        $histograms = [];
        foreach ($numericIds as $qid) {
            $values = $rows->map(fn ($r) => $r->answers[$qid] ?? null)
                ->filter(fn ($v) => is_numeric($v))
                ->map(fn ($v) => (int) $v)
                ->values();
            $means[$qid] = [
                'count' => $values->count(),
                'avg' => $values->count() ? round($values->avg(), 2) : null,
            ];
            $hist = [];
            foreach ($values as $v) {
                $hist[(string) $v] = ($hist[(string) $v] ?? 0) + 1;
            }
            ksort($hist, SORT_NUMERIC);
            $histograms[$qid] = $hist;
        }

        $sectionAvgs = [];
        foreach (ExperienceSurveyDefinition::sections() as $section) {
            $ids = collect($section['questions'])
                ->filter(fn ($q) => in_array($q['type'], ['likert5', 'nps'], true))
                ->pluck('id')
                ->all();
            if (!$ids) {
                continue;
            }
            $vals = [];
            foreach ($ids as $qid) {
                if (($means[$qid]['avg'] ?? null) !== null) {
                    $vals[] = $means[$qid]['avg'];
                }
            }
            $sectionAvgs[$section['id']] = [
                'title' => $section['title'],
                'avg' => $vals ? round(array_sum($vals) / count($vals), 2) : null,
                'questions' => $ids,
            ];
        }

        return response()->json([
            'total_responses' => $total,
            'section_averages' => $sectionAvgs,
            'question_means' => $means,
            'histograms' => $histograms,
            'distributions' => [
                'A1' => $this->optionDistribution($rows, 'A1'),
                'A4' => $this->optionDistribution($rows, 'A4'),
                'A6' => $this->optionDistribution($rows, 'A6'),
                'C1' => $this->optionDistribution($rows, 'C1'),
                'C3' => $this->optionDistribution($rows, 'C3'),
                'D0' => $this->optionDistribution($rows, 'D0'),
                'D4_1' => $this->optionDistribution($rows, 'D4_1'),
                'E1' => $this->optionDistribution($rows, 'E1'),
                'E4' => $this->optionDistribution($rows, 'E4'),
            ],
            'charts' => [
                'section_avg' => collect($sectionAvgs)
                    ->filter(fn ($s) => $s['avg'] !== null)
                    ->map(fn ($s, $id) => ['id' => $id, 'label' => $id, 'title' => $s['title'], 'avg' => $s['avg']])
                    ->values(),
                'personalization_likert' => collect(['B1', 'B2', 'B3', 'C2', 'E3'])
                    ->map(fn ($qid) => [
                        'id' => $qid,
                        'label' => match ($qid) {
                            'B1' => 'Cảm nhận CNH',
                            'B2' => 'Gợi ý liên quan',
                            'B3' => 'Khác biệt CNH',
                            'C2' => 'Hữu ích CNH',
                            default => 'Đáng phát triển',
                        },
                        'avg' => $means[$qid]['avg'] ?? null,
                    ])
                    ->values(),
                'ai_usage' => $this->labeledDist($rows, 'D0', [
                    'chatbot' => 'Chatbot',
                    'career' => 'Career',
                    'study' => 'Quản lý học tập',
                    'none' => 'Chưa dùng AI',
                ]),
                'most_useful' => $this->labeledDist($rows, 'D4_1', [
                    'chatbot' => 'Chatbot',
                    'career' => 'Career',
                    'study' => 'Quản lý học tập',
                ]),
                'satisfaction' => $this->labeledDist($rows, 'E1', [
                    '1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5',
                ]),
                'nps' => collect(range(0, 10))->map(fn ($n) => [
                    'label' => (string) $n,
                    'value' => (int) (($histograms['E4'][(string) $n] ?? 0)),
                ])->values(),
                'personalization' => $this->labeledDist($rows, 'C1', [
                    'content' => 'Gợi ý nội dung',
                    'path' => 'Bước học tiếp',
                    'review' => 'Ôn tập/tóm tắt',
                    'career' => 'Nghề/CV',
                    'overview' => 'Tổng quan',
                    'none' => 'Chưa rõ rệt',
                    'other' => 'Khác',
                ]),
            ],
            'recent' => $rows->take(30)->map(fn ($r) => $this->serializeRow($r))->values(),
        ]);
    }

    public function adminIndex(Request $request): JsonResponse
    {
        $perPage = min(100, max(10, (int) $request->input('per_page', 20)));
        $paginator = ExperienceSurveyResponse::query()
            ->with(['user:id,name,email,student_code'])
            ->orderByDesc('submitted_at')
            ->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())->map(fn ($r) => $this->serializeRow($r, full: true))->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function adminExport(Request $request): StreamedResponse
    {
        $filename = 'experience_survey_' . now()->format('Ymd_His') . '.csv';
        $headers = ExperienceSurveyDefinition::exportHeaders();
        $questionMap = ExperienceSurveyDefinition::questionMap();

        return response()->streamDownload(function () use ($headers, $questionMap) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, $headers);

            ExperienceSurveyResponse::query()
                ->with(['user:id,name,email,student_code'])
                ->orderBy('id')
                ->chunk(200, function ($chunk) use ($out, $questionMap) {
                    foreach ($chunk as $row) {
                        $line = [
                            $row->id,
                            $row->user_id,
                            $row->user?->student_code ?? '',
                            $row->user?->name ?? '',
                            $row->user?->email ?? '',
                            optional($row->submitted_at)->toDateTimeString() ?? '',
                            optional($row->updated_at)->toDateTimeString() ?? '',
                            $row->survey_version,
                        ];
                        foreach (array_keys($questionMap) as $qid) {
                            $val = $row->answers[$qid] ?? '';
                            if (is_array($val)) {
                                $val = implode('|', $val);
                            }
                            $line[] = $val;
                        }
                        fputcsv($out, $line);
                    }
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function validateAnswers(array $answers): array
    {
        $errors = [];
        $aiUsed = $this->aiFeaturesUsed($answers);
        $map = ExperienceSurveyDefinition::questionMap();

        foreach (['A6', 'C1', 'D0'] as $exclusive) {
            $vals = $answers[$exclusive] ?? [];
            if (is_array($vals) && in_array('none', $vals, true) && count($vals) > 1) {
                $errors[$exclusive] = ['Nếu chọn "Chưa…" thì không chọn thêm đáp án khác.'];
            }
        }

        foreach ($map as $qid => $q) {
            if (($q['show_if_ai'] ?? null) && !in_array($q['show_if_ai'], $aiUsed, true)) {
                continue;
            }
            if (($q['show_if_any_ai'] ?? false) && !$aiUsed) {
                continue;
            }

            if ($qid === 'A6_other') {
                $a6 = $answers['A6'] ?? [];
                if (!is_array($a6) || !in_array('other', $a6, true)) {
                    continue;
                }
                if ($this->isEmptyAnswer($answers[$qid] ?? null, 'text')) {
                    $errors[$qid] = ['Vui lòng ghi rõ hệ thống khác.'];
                }
                continue;
            }
            if ($qid === 'C1_other') {
                $c1 = $answers['C1'] ?? [];
                if (!is_array($c1) || !in_array('other', $c1, true)) {
                    continue;
                }
                if ($this->isEmptyAnswer($answers[$qid] ?? null, 'text')) {
                    $errors[$qid] = ['Vui lòng ghi rõ khía cạnh khác.'];
                }
                continue;
            }

            if (!($q['required'] ?? false)) {
                continue;
            }

            $val = $answers[$qid] ?? null;
            if ($this->isEmptyAnswer($val, $q['type'])) {
                $errors[$qid] = ['Câu hỏi này là bắt buộc.'];
                continue;
            }

            $typeErr = $this->typeError($val, $q);
            if ($typeErr) {
                $errors[$qid] = [$typeErr];
            }
        }

        return $errors;
    }

    private function normalizeAnswers(array $answers): array
    {
        $out = [];
        $aiUsed = $this->aiFeaturesUsed($answers);
        $map = ExperienceSurveyDefinition::questionMap();

        foreach ($map as $qid => $q) {
            if (($q['show_if_ai'] ?? null) && !in_array($q['show_if_ai'], $aiUsed, true)) {
                continue;
            }
            if (($q['show_if_any_ai'] ?? false) && !$aiUsed) {
                continue;
            }
            if (!array_key_exists($qid, $answers)) {
                continue;
            }
            $val = $answers[$qid];
            if ($q['type'] === 'multi') {
                $out[$qid] = array_values(array_unique(array_map('strval', (array) $val)));
            } elseif (in_array($q['type'], ['likert5', 'nps'], true)) {
                $out[$qid] = is_numeric($val) ? (int) $val : $val;
            } else {
                $out[$qid] = is_string($val) ? trim($val) : $val;
            }
        }

        return $out;
    }

    private function aiFeaturesUsed(array $answers): array
    {
        $d0 = $answers['D0'] ?? [];
        if (!is_array($d0) || in_array('none', $d0, true)) {
            return [];
        }

        return array_values(array_intersect($d0, ['chatbot', 'career', 'study']));
    }

    private function isEmptyAnswer(mixed $val, string $type): bool
    {
        if ($type === 'multi') {
            return !is_array($val) || count($val) === 0;
        }
        if (in_array($type, ['likert5', 'nps'], true)) {
            return $val === null || $val === '';
        }

        return $val === null || (is_string($val) && trim($val) === '');
    }

    private function typeError(mixed $val, array $q): ?string
    {
        return match ($q['type']) {
            'likert5' => (is_numeric($val) && (int) $val >= 1 && (int) $val <= 5) ? null : 'Chọn điểm từ 1 đến 5.',
            'nps' => (is_numeric($val) && (int) $val >= 0 && (int) $val <= 10) ? null : 'Chọn điểm từ 0 đến 10.',
            'single' => $this->optionExists($val, $q) ? null : 'Lựa chọn không hợp lệ.',
            'multi' => $this->optionsExist((array) $val, $q) ? null : 'Lựa chọn không hợp lệ.',
            default => null,
        };
    }

    private function optionExists(mixed $val, array $q): bool
    {
        $allowed = collect($q['options'] ?? [])->pluck('value')->all();

        return in_array((string) $val, $allowed, true);
    }

    private function optionsExist(array $vals, array $q): bool
    {
        $allowed = collect($q['options'] ?? [])->pluck('value')->all();
        foreach ($vals as $v) {
            if (!in_array((string) $v, $allowed, true)) {
                return false;
            }
        }

        return true;
    }

    private function numericQuestionIds(array $types): array
    {
        return collect(ExperienceSurveyDefinition::questionMap())
            ->filter(fn ($q) => in_array($q['type'], $types, true))
            ->keys()
            ->all();
    }

    private function optionDistribution($rows, string $qid): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $val = $row->answers[$qid] ?? null;
            $items = is_array($val) ? $val : (($val === null || $val === '') ? [] : [$val]);
            foreach ($items as $item) {
                $key = (string) $item;
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }
        arsort($counts);

        return $counts;
    }

    private function labeledDist($rows, string $qid, array $labels): array
    {
        $raw = $this->optionDistribution($rows, $qid);
        $out = [];
        foreach ($labels as $key => $label) {
            $out[] = [
                'key' => (string) $key,
                'label' => $label,
                'value' => (int) ($raw[(string) $key] ?? 0),
            ];
        }
        foreach ($raw as $key => $value) {
            if (!array_key_exists($key, $labels)) {
                $out[] = ['key' => $key, 'label' => $key, 'value' => (int) $value];
            }
        }

        return $out;
    }

    private function serializeRow(ExperienceSurveyResponse $row, bool $full = false): array
    {
        $data = [
            'id' => $row->id,
            'user_id' => $row->user_id,
            'student_code' => $row->user?->student_code,
            'student_name' => $row->user?->name,
            'student_email' => $row->user?->email,
            'submitted_at' => $row->submitted_at?->toIso8601String(),
            'updated_at' => $row->updated_at?->toIso8601String(),
            'survey_version' => $row->survey_version,
            'A1' => $row->answers['A1'] ?? null,
            'A2' => $row->answers['A2'] ?? null,
            'D0' => $row->answers['D0'] ?? [],
            'E1' => $row->answers['E1'] ?? null,
            'E4' => $row->answers['E4'] ?? null,
        ];
        if ($full) {
            $data['answers'] = $row->answers;
        }

        return $data;
    }
}
