<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VideoInteraction extends Model
{
    use HasFactory;

    public const TYPE_MULTIPLE_CHOICE = 'multiple_choice';

    public const TYPE_TRUE_FALSE = 'true_false';

    public const TYPE_SHORT_ANSWER = 'short_answer';

    public const TYPES = [
        self::TYPE_MULTIPLE_CHOICE => 'Trắc nghiệm (1 đáp án đúng)',
        self::TYPE_TRUE_FALSE => 'Đúng / Sai',
        self::TYPE_SHORT_ANSWER => 'Trả lời ngắn',
    ];

    protected $fillable = [
        'course_id',
        'lesson_id',
        'document_id',
        'timestamp_seconds',
        'question_type',
        'question',
        'options',
        'correct_answer',
        'explanation',
        'is_required',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'timestamp_seconds' => 'integer',
            'options' => 'array',
            'is_required' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(VideoInteractionResponse::class);
    }

    public function formattedTimestamp(): string
    {
        $secs = max(0, (int) $this->timestamp_seconds);
        $hours = intdiv($secs, 3600);
        $minutes = intdiv($secs % 3600, 60);
        $seconds = $secs % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->question_type] ?? 'Trắc nghiệm';
    }

    public function typeBadgeClass(): string
    {
        return match ($this->question_type) {
            self::TYPE_TRUE_FALSE => 'bg-info-subtle text-info-emphasis border border-info',
            self::TYPE_SHORT_ANSWER => 'bg-warning-subtle text-warning-emphasis border border-warning',
            default => 'bg-primary-subtle text-primary border border-primary',
        };
    }

    public function checkAnswer(string $answer): bool
    {
        $submitted = trim(mb_strtolower($answer));
        $expected = trim(mb_strtolower((string) $this->correct_answer));

        if ($this->question_type === self::TYPE_SHORT_ANSWER) {
            $acceptedAnswers = array_filter(array_map(
                fn (string $item) => trim(mb_strtolower($item)),
                explode('|', (string) $this->correct_answer)
            ));

            return in_array($submitted, $acceptedAnswers, true);
        }

        return $submitted === $expected;
    }

    public function correctAnswerLabel(): string
    {
        if ($this->question_type === self::TYPE_TRUE_FALSE) {
            return $this->correct_answer === 'true' ? 'Đúng' : 'Sai';
        }

        if ($this->question_type === self::TYPE_MULTIPLE_CHOICE && is_array($this->options)) {
            $key = strtoupper((string) $this->correct_answer);
            $text = $this->options[$key] ?? $this->options[$this->correct_answer] ?? null;

            return $text ? "{$key}. {$text}" : $key;
        }

        return (string) $this->correct_answer;
    }
}
