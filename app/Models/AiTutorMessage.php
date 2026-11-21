<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiTutorMessage extends Model
{
    use HasFactory;

    public const ROLE_USER = 'user';

    public const ROLE_ASSISTANT = 'assistant';

    protected $fillable = [
        'user_id',
        'course_id',
        'lesson_id',
        'role',
        'content',
        'image_path',
        'video_timestamp_seconds',
        'gemini_interaction_id',
        'sources_used',
    ];

    protected function casts(): array
    {
        return [
            'video_timestamp_seconds' => 'integer',
            'sources_used' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function formattedTimestamp(): ?string
    {
        if ($this->video_timestamp_seconds === null) {
            return null;
        }

        $secs = max(0, (int) $this->video_timestamp_seconds);
        $minutes = intdiv($secs, 60);
        $seconds = $secs % 60;

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    public function imageUrl(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return route('student.ai-tutor.image', $this);
    }
}
