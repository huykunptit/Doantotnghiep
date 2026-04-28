<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizAttempt extends Model
{
    protected $fillable = [
        'user_id',
        'quiz_id',
        'status',           // in_progress | paused | submitted | force_stopped
        'score',
        'passed',
        'question_ids',
        'answers_json',
        'started_at',
        'completed_at',
        'paused_at',
        'resumed_at',
        'paused_duration',  // Total seconds spent paused
        'time_extensions',  // Total seconds of admin-granted extensions
        'auto_saved_at',
        'force_stop_reason',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'question_ids'    => 'array',
        'answers_json'    => 'array',
        'passed'          => 'boolean',
        'started_at'      => 'datetime',
        'completed_at'    => 'datetime',
        'paused_at'       => 'datetime',
        'resumed_at'      => 'datetime',
        'auto_saved_at'   => 'datetime',
        'paused_duration' => 'integer',
        'time_extensions' => 'integer',
    ];

    // ── Relationships ───────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function violations(): HasMany
    {
        return $this->hasMany(ExamViolation::class, 'attempt_id');
    }

    // ── State checks ────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, ['submitted', 'force_stopped']);
    }

    /**
     * Calculate remaining time in seconds.
     * Returns null if quiz has no time limit.
     */
    public function remainingTime(): ?int
    {
        $quiz = $this->quiz;
        if (!$quiz || !$quiz->time_limit) {
            return null;
        }

        $totalAllowed = ($quiz->time_limit * 60) + ($this->time_extensions ?? 0);
        $elapsed = $this->elapsedActiveSeconds();

        return max(0, $totalAllowed - $elapsed);
    }

    /**
     * Calculate total active (non-paused) seconds since start.
     */
    public function elapsedActiveSeconds(): int
    {
        if (!$this->started_at) return 0;

        $totalElapsed = now()->diffInSeconds($this->started_at);
        $pausedSeconds = $this->paused_duration ?? 0;

        // If currently paused, add the current pause duration
        if ($this->isPaused() && $this->paused_at) {
            $pausedSeconds += now()->diffInSeconds($this->paused_at);
        }

        return max(0, $totalElapsed - $pausedSeconds);
    }

    /**
     * Check if time has expired (server-side authority).
     */
    public function isTimeExpired(): bool
    {
        $remaining = $this->remainingTime();
        return $remaining !== null && $remaining <= 0;
    }
}
