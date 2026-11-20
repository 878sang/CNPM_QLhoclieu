<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'course_id', 'lesson_id', 'title', 'description',
        'type', 'question_types', 'question_count', 'time_limit_minutes', 'deadline',
        'answer_key', 'file_path', 'answer_file_path',
        'original_name', 'answer_original_name', 'status', 'show_answer_to_students',
        'submission_mode',
        'rejection_reason', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'answer_key' => 'array',
        'question_types' => 'array',
        'deadline' => 'datetime',
        'graded_at' => 'datetime',
        'show_answer_to_students' => 'boolean',
        'reviewed_at' => 'datetime',
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
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'Chờ duyệt',
            'published' => 'Đã duyệt',
            'rejected' => 'Bị từ chối',
            default => 'Bản nháp',
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'pending' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
            'published' => 'bg-success-subtle text-success border border-success-subtle',
            'rejected' => 'bg-danger-subtle text-danger border border-danger-subtle',
            default => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
        };
    }

    public function isFileSubmission(): bool
    {
        return ($this->submission_mode ?? 'answers') === 'file';
    }

    public function isAnswerSubmission(): bool
    {
        return ! $this->isFileSubmission();
    }

    public function submissionModeLabel(): string
    {
        return $this->isFileSubmission() ? 'Nộp file bài làm' : 'Chọn / nhập đáp án';
    }

    public function isMultipleChoice(): bool
    {
        return $this->type === 'multiple_choice';
    }

    public function isMixed(): bool
    {
        return $this->type === 'mixed';
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'multiple_choice' => 'Trắc nghiệm',
            'mixed' => 'Hỗn hợp (TN & Tự luận)',
            default => 'Tự luận / Nhập số',
        };
    }

    public function questionType(int $questionNumber): string
    {
        if (! empty($this->question_types)) {
            if (isset($this->question_types[(string) $questionNumber])) {
                return $this->question_types[(string) $questionNumber];
            }
            if (isset($this->question_types[$questionNumber])) {
                return $this->question_types[$questionNumber];
            }
        }

        return $this->type === 'multiple_choice' ? 'multiple_choice' : 'text';
    }

    public function isMultipleChoiceQuestion(int $questionNumber): bool
    {
        return $this->questionType($questionNumber) === 'multiple_choice';
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
        return (float) ($this->answer_key ? count($this->answer_key) : ($this->question_count ?: 10.0));
    }
}
