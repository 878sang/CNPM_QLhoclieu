<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Submission extends Model
{
    protected $fillable = [
        'assignment_id', 'user_id', 'answers', 'file_path', 'original_name',
        'score', 'max_score', 'feedback', 'graded_by', 'graded_at', 'submitted_at',
    ];

    protected $casts = [
        'answers' => 'array',
        'graded_at' => 'datetime',
        'submitted_at' => 'datetime',
        'score' => 'float',
        'max_score' => 'float',
    ];

    // ─── Relationships ───────────────────────────────────────────────

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    public function isGraded(): bool
    {
        return $this->score !== null;
    }

    public function scorePercent(): float
    {
        if (! $this->max_score || $this->max_score == 0) {
            return 0.0;
        }

        return round(($this->score / $this->max_score) * 100, 1);
    }

    public function getAnswerFor(int $n): ?string
    {
        return $this->answers[$n] ?? $this->answers[(string) $n] ?? null;
    }
}
