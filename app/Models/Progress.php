<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Progress extends Model
{
    use HasFactory;

    protected $table = 'progress';

    protected $fillable = [
        'user_id',
        'course_id',
        'lesson_id',
        'is_completed',
        'last_position_seconds',
        'duration_seconds',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
            'last_position_seconds' => 'integer',
            'duration_seconds' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function effectiveDuration(): ?int
    {
        if ($this->duration_seconds && $this->duration_seconds > 0) {
            return $this->duration_seconds;
        }

        if ($this->relationLoaded('lesson') && $this->lesson && $this->lesson->relationLoaded('progresses')) {
            $known = $this->lesson->progresses->firstWhere(fn ($p) => ($p->duration_seconds ?? 0) > 0)?->duration_seconds;
            if ($known) {
                return $known;
            }
        }

        return static::where('lesson_id', $this->lesson_id)
            ->whereNotNull('duration_seconds')
            ->where('duration_seconds', '>', 0)
            ->value('duration_seconds');
    }

    public function watchedPercentage(): int
    {
        if ($this->is_completed) {
            return 100;
        }

        $dur = $this->effectiveDuration();
        if ($dur && $dur > 0) {
            return (int) min(100, round(($this->last_position_seconds / $dur) * 100));
        }

        return 0;
    }

    public function formattedPosition(): string
    {
        return gmdate($this->last_position_seconds >= 3600 ? 'H:i:s' : 'i:s', $this->last_position_seconds ?? 0);
    }

    public function formattedDuration(): string
    {
        $dur = $this->effectiveDuration();
        if (! $dur) {
            return '—';
        }

        return gmdate($dur >= 3600 ? 'H:i:s' : 'i:s', $dur);
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
}
