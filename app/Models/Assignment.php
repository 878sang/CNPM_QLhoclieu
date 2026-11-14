<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends Model
{
    protected $fillable = [
        'course_id', 'lesson_id', 'title', 'description',
        'type', 'question_count', 'time_limit_minutes', 'deadline',
        'answer_key', 'file_path', 'answer_file_path',
        'original_name', 'answer_original_name', 'status', 'show_answer_to_students',
    ];

    protected $casts = [
        'answer_key' => 'array',
        'deadline' => 'datetime',
        'graded_at' => 'datetime',
        'show_answer_to_students' => 'boolean',
    ];

    // ─── Relationships ───────────────────────────────────────────────

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isMultipleChoice(): bool
    {
        return $this->type === 'multiple_choice';
    }

    public function isPastDeadline(): bool
    {
        return $this->deadline && now()->gt($this->deadline);
    }

    /** Lấy đáp án cho câu hỏi thứ $n (1-indexed) */
    public function getAnswerFor(int $n): ?string
    {
        return $this->answer_key[$n] ?? $this->answer_key[(string) $n] ?? null;
    }

    /** Chấm điểm tự động so sánh $answers với answer_key */
    public function autoGrade(array $answers): float
    {
        if (! $this->answer_key) {
            return 0.0;
        }

        $correct = 0;
        foreach ($this->answer_key as $q => $correctAnswer) {
            $submitted = $answers[$q] ?? $answers[(string) $q] ?? null;
            if ($submitted !== null && strtoupper(trim($submitted)) === strtoupper(trim($correctAnswer))) {
                $correct++;
            }
        }

        return $correct;
    }

    public function maxScore(): float
    {
        return (float) ($this->answer_key ? count($this->answer_key) : $this->question_count);
    }
}
