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

    public function statusLabel(): string
    {
        return $this->isPublished() ? 'Đã xuất bản' : 'Bản nháp';
    }

    public function statusTone(): string
    {
        return $this->isPublished() ? 'success' : 'warning';
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function progresses(): HasMany
    {
        return $this->hasMany(Progress::class);
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
}
