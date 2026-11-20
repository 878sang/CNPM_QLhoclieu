<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = ['chapter_id', 'title', 'position', 'status'];

    public function isDraft(): bool
    {
        return ($this->status ?? 'draft') === 'draft';
    }

    public function isPublished(): bool
    {
        return ($this->status ?? '') === 'published';
    }

    public function isPending(): bool
    {
        return ($this->status ?? '') === 'pending';
    }

    public function isRejected(): bool
    {
        return ($this->status ?? '') === 'rejected';
    }

    public function statusLabel(): string
    {
        return match ($this->status ?? 'draft') {
            'published' => 'Đã xuất bản',
            'pending' => 'Chờ Quản trị viên duyệt',
            'rejected' => 'Bị từ chối',
            default => 'Bản nháp',
        };
    }

    public function statusTone(): string
    {
        return match ($this->status ?? 'draft') {
            'published' => 'success',
            'pending' => 'warning',
            'rejected' => 'danger',
            default => 'warning',
        };
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function progresses(): HasMany
    {
        return $this->hasMany(Progress::class);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(LessonInteraction::class);
    }

    public function videoInteractions(): HasMany
    {
        return $this->hasMany(VideoInteraction::class)->orderBy('timestamp_seconds');
    }

    public function isCompletedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->progresses()
            ->where('user_id', $user->id)
            ->where('is_completed', true)
            ->exists();
    }

    public function lastPositionFor(?User $user): int
    {
        if (! $user) {
            return 0;
        }

        return (int) ($this->progresses()
            ->where('user_id', $user->id)
            ->value('last_position_seconds') ?? 0);
    }

    public function progressPercentageFor(?User $user): int
    {
        if (! $user) {
            return 0;
        }

        $progress = $this->relationLoaded('progresses')
            ? $this->progresses->firstWhere('user_id', $user->id)
            : $this->progresses()->where('user_id', $user->id)->first();

        if (! $progress) {
            return 0;
        }

        if ($progress->is_completed) {
            return 100;
        }

        $hasVideo = $this->relationLoaded('documents')
            ? $this->documents->contains(fn ($d) => $d->isVideo())
            : $this->documents()->get()->contains(fn ($d) => $d->isVideo());

        if ($hasVideo) {
            return $progress->watchedPercentage();
        }

        return 0;
    }

    /**
     * Tính Điểm tương tác video của bài học cho học viên.
     *
     * @return array{total: int, answered: int, correct: int, score_10: ?float, accuracy_percent: ?int}
     */
    public function videoInteractionStatsFor(?User $user): array
    {
        if (! $user) {
            return ['total' => 0, 'answered' => 0, 'correct' => 0, 'score_10' => null, 'accuracy_percent' => null];
        }

        $interactions = $this->relationLoaded('documents')
            ? $this->documents->filter(fn ($d) => $d->isVideo())->flatMap(fn ($d) => $d->relationLoaded('videoInteractions') ? $d->videoInteractions : $d->videoInteractions()->get())
            : $this->videoInteractions()->with(['responses' => fn ($q) => $q->where('user_id', $user->id)])->get();

        $total = $interactions->count();
        if ($total === 0) {
            return ['total' => 0, 'answered' => 0, 'correct' => 0, 'score_10' => null, 'accuracy_percent' => null];
        }

        $answered = 0;
        $correct = 0;
        foreach ($interactions as $vi) {
            $resp = $vi->relationLoaded('responses')
                ? $vi->responses->firstWhere('user_id', $user->id)
                : $vi->responses()->where('user_id', $user->id)->first();
            if ($resp) {
                $answered++;
                if ($resp->is_correct) {
                    $correct++;
                }
            }
        }

        return [
            'total' => $total,
            'answered' => $answered,
            'correct' => $correct,
            'score_10' => round($correct * 10 / $total, 1),
            'accuracy_percent' => (int) round($correct * 100 / $total),
        ];
    }
}
