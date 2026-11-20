<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LessonInteraction extends Model
{
    use HasFactory;

    public const TYPE_QUESTION = 'question';

    public const TYPE_COMMENT = 'comment';

    public const TYPE_DISCUSSION = 'discussion';

    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_HIDDEN = 'hidden';

    public const TYPES = [
        self::TYPE_QUESTION => 'Câu hỏi cho giảng viên',
        self::TYPE_DISCUSSION => 'Thảo luận / Bình luận',
    ];

    public const STATUSES = [
        self::STATUS_OPEN => 'Chờ giải đáp',
        self::STATUS_RESOLVED => 'Đã giải đáp',
        self::STATUS_HIDDEN => 'Đã ẩn',
    ];

    protected $fillable = [
        'course_id',
        'lesson_id',
        'user_id',
        'parent_id',
        'type',
        'content',
        'video_timestamp_seconds',
        'status',
        'is_pinned',
        'likes_count',
    ];

    protected function casts(): array
    {
        return [
            'video_timestamp_seconds' => 'integer',
            'is_pinned' => 'boolean',
            'likes_count' => 'integer',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('created_at');
    }

    public function likedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'lesson_interaction_likes')->withTimestamps();
    }

    public function isQuestion(): bool
    {
        return $this->type === self::TYPE_QUESTION;
    }

    public function typeLabel(): string
    {
        if ($this->type === self::TYPE_COMMENT) {
            return self::TYPES[self::TYPE_DISCUSSION];
        }

        return self::TYPES[$this->type] ?? 'Thảo luận / Bình luận';
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? 'Đang mở';
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_RESOLVED => 'bg-success-subtle text-success border border-success',
            self::STATUS_HIDDEN => 'bg-secondary-subtle text-secondary border border-secondary',
            default => 'bg-warning-subtle text-warning-emphasis border border-warning',
        };
    }

    public function formattedVideoTimestamp(): ?string
    {
        if ($this->video_timestamp_seconds === null) {
            return null;
        }

        $secs = max(0, (int) $this->video_timestamp_seconds);
        $minutes = intdiv($secs, 60);
        $seconds = $secs % 60;

        return sprintf('%02d:%02d', $minutes, $seconds);
    }
}
