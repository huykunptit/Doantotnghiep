<?php

namespace Database\Seeders\Support;

use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Support\Collection;

/**
 * Tạo bài làm quiz/đề thi demo kèm answers_json khớp điểm.
 */
class SeededQuizAttempt
{
    public static function upsert(
        int $userId,
        Quiz $quiz,
        float $scorePercent,
        mixed $startedAt,
        mixed $completedAt,
        array $extra = [],
    ): QuizAttempt {
        $questions = self::questionsOf($quiz);
        $questionIds = $questions->pluck('id')->values()->all();
        $total = max(1, $questions->count());
        $correctCount = (int) round(max(0, min($total, ($scorePercent / 100) * $total)));

        return QuizAttempt::query()->updateOrCreate(
            ['user_id' => $userId, 'quiz_id' => $quiz->id],
            array_merge([
                'status' => 'submitted',
                'score' => round($scorePercent, 2),
                'passed' => $scorePercent >= (int) ($quiz->pass_score ?? 50),
                'question_ids' => $questionIds,
                'answers_json' => self::buildAnswers($questions, $correctCount, $userId + (int) $quiz->id),
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
                'ip_address' => '10.8.'.(($userId % 250) + 1).'.'.(($quiz->id % 250) + 1),
                'user_agent' => 'EriptDemoSeeder',
            ], $extra),
        );
    }

    public static function questionsOf(Quiz $quiz): Collection
    {
        if ($quiz->relationLoaded('questions') && $quiz->questions->isNotEmpty()) {
            $quiz->questions->loadMissing('answers');

            return $quiz->questions->values();
        }

        return $quiz->questions()->with('answers')->orderByPivot('order')->get()->values();
    }

    public static function buildAnswers(Collection $questions, int $correctCount, int $salt): array
    {
        $payload = [];
        foreach ($questions->values() as $index => $question) {
            $payload[$question->id] = self::pickAnswer($question, $index < $correctCount, $salt + $index);
        }

        return $payload;
    }

    private static function pickAnswer(Question $question, bool $wantCorrect, int $salt): mixed
    {
        $correct = $question->answers->where('is_correct', true)->values();
        $wrong = $question->answers->where('is_correct', false)->values();

        return match ($question->type) {
            'multiple_choice' => $wantCorrect
                ? $correct->pluck('id')->all()
                : ($wrong->isNotEmpty() ? [$wrong[$salt % $wrong->count()]->id] : $correct->take(1)->pluck('id')->all()),
            'short_answer', 'numerical' => $wantCorrect
                ? ($correct->first()?->content ?? '')
                : ($question->type === 'numerical' ? '0' : 'không rõ'),
            'matching' => $wantCorrect
                ? $question->answers->mapWithKeys(fn ($answer) => [$answer->id => $answer->sub_content])->all()
                : self::shuffledMatching($question, $salt),
            default => $wantCorrect
                ? $correct->first()?->id
                : ($wrong->isNotEmpty() ? $wrong[$salt % $wrong->count()]->id : $correct->first()?->id),
        };
    }

    private static function shuffledMatching(Question $question, int $salt): array
    {
        $values = $question->answers->pluck('sub_content')->values()->all();
        if ($values !== []) {
            $pivot = $salt % count($values);
            $values = array_merge(array_slice($values, $pivot), array_slice($values, 0, $pivot));
        }
        $ids = $question->answers->pluck('id')->values();

        return $ids->mapWithKeys(fn ($id, $index) => [$id => $values[$index] ?? ''])->all();
    }
}
