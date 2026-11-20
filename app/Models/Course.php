<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Storage;

class Course extends Model
{
    use HasFactory;

    /** @var array<int, int> */
    private array $progressPercentageCache = [];

    /** @var array<int, ?Lesson> */
    private array $lastLessonCache = [];

    /** @var array<int, array{total: int, answered: int, correct: int, score_10: ?float, accuracy_percent: ?int}> */
    private array $videoQuizStatsCache = [];

    protected $fillable = ['subject_id', 'teacher_id', 'title', 'description', 'cover_image', 'start_at', 'end_at', 'status'];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
        ];
    }

    /**
     * Thêm trackable_documents_count và completed_documents_count cho học viên đã cho.
     */
    public function scopeWithProgressFor(Builder $query, User $user): void
    {
        $query->withCount([
            'documents as trackable_documents_count' => fn (Builder $documents) => $documents->trackable(),
            'documents as completed_documents_count' => fn (Builder $documents) => $documents->trackable()->whereHas(
                'progress',
                fn (Builder $progress) => $progress->where('user_id', $user->id)->whereNotNull('completed_at'),
            ),
        ]);
    }

    /**
     * Phần trăm tài liệu đã hoàn thành (0 nếu khóa học chưa có tài liệu nào để học).
     */
    protected function progressPercent(): Attribute
    {
        return Attribute::get(function (): int {
            $total = (int) ($this->trackable_documents_count ?? 0);

            return $total > 0 ? (int) round((int) $this->completed_documents_count * 100 / $total) : 0;
        });
    }

    public function getCoverUrlAttribute(): string
    {
        if ($this->cover_image && Storage::disk('public')->exists($this->cover_image)) {
            return Storage::disk('public')->url($this->cover_image);
        }

        return asset('images/default-course.svg');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class)->orderBy('position');
    }

    public function lessons(): HasManyThrough
    {
        return $this->hasManyThrough(Lesson::class, Chapter::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function progresses(): HasMany
    {
        return $this->hasMany(Progress::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class)->orderBy('created_at', 'desc');
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(LessonInteraction::class);
    }

    public function videoInteractions(): HasMany
    {
        return $this->hasMany(VideoInteraction::class)->orderBy('timestamp_seconds');
    }

    public function appNotifications(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }

    public function progressPercentageFor(?User $user): int
    {
        if (! $user) {
            return 0;
        }

        if (array_key_exists($user->id, $this->progressPercentageCache)) {
            return $this->progressPercentageCache[$user->id];
        }

        // Tận dụng dữ liệu đã eager-load để tránh N+1 query trong các vòng lặp danh sách khóa học / học viên
        if ($this->relationLoaded('chapters') && $this->chapters->every(fn ($ch) => $ch->relationLoaded('lessons'))) {
            $lessons = $this->chapters
                ->pluck('lessons')
                ->flatten()
                ->filter(fn ($lesson) => ($lesson->status ?? 'draft') === 'published')
                ->values();

            $totalLessons = $lessons->count();
            if ($totalLessons === 0) {
                return $this->progressPercentageCache[$user->id] = 0;
            }

            $progressByLesson = $this->relationLoaded('progresses')
                ? $this->progresses->where('user_id', $user->id)->keyBy('lesson_id')
                : $this->progresses()->where('user_id', $user->id)->get()->keyBy('lesson_id');

            $totalScore = 0;
            foreach ($lessons as $lesson) {
                $prog = $progressByLesson->get($lesson->id);
                if (! $prog) {
                    continue;
                }

                if ($prog->is_completed) {
                    $totalScore += 100;
                } else {
                    $hasVideo = $lesson->relationLoaded('documents')
                        ? $lesson->documents->contains(fn ($d) => $d->isApproved() && $d->isVideo())
                        : $lesson->documents()->approved()->get()->contains(fn ($d) => $d->isVideo());

                    if ($hasVideo) {
                        $totalScore += $prog->watchedPercentage();
                    }
                }
            }

            return $this->progressPercentageCache[$user->id] = (int) min(100, round($totalScore / $totalLessons));
        }

        $lessons = $this->lessons()
            ->where('status', 'published')
            ->with(['documents' => fn ($q) => $q->approved(), 'progresses' => fn ($q) => $q->where('user_id', $user->id)])
            ->get();
        $totalLessons = $lessons->count();
        if ($totalLessons === 0) {
            return $this->progressPercentageCache[$user->id] = 0;
        }

        $totalScore = 0;
        foreach ($lessons as $lesson) {
            $prog = $lesson->progresses->first();
            if (! $prog) {
                continue;
            }

            if ($prog->is_completed) {
                $totalScore += 100;
            } else {
                $hasVideo = $lesson->documents->contains(fn ($d) => $d->isVideo());
                if ($hasVideo) {
                    $totalScore += $prog->watchedPercentage();
                }
            }
        }

        return $this->progressPercentageCache[$user->id] = (int) min(100, round($totalScore / $totalLessons));
    }

    public function progressStatusFor(?User $user): string
    {
        $percentage = $this->progressPercentageFor($user);

        if ($percentage === 0) {
            return 'not_started';
        }

        if ($percentage >= 100) {
            return 'completed';
        }

        return 'in_progress';
    }

    public function progressStatusLabel(?User $user): string
    {
        return match ($this->progressStatusFor($user)) {
            'completed' => 'Hoàn thành',
            'in_progress' => 'Đang học',
            default => 'Chưa bắt đầu',
        };
    }

    public function progressStatusTone(?User $user): string
    {
        return match ($this->progressStatusFor($user)) {
            'completed' => 'success',
            'in_progress' => 'primary',
            default => 'secondary',
        };
    }

    public function lastStudiedLessonFor(?User $user): ?Lesson
    {
        if (! $user) {
            return $this->chapters()->first()?->lessons()->where('status', 'published')->first();
        }

        if (array_key_exists($user->id, $this->lastLessonCache)) {
            return $this->lastLessonCache[$user->id];
        }

        if ($this->relationLoaded('chapters') && $this->chapters->every(fn ($ch) => $ch->relationLoaded('lessons'))) {
            $publishedLessons = $this->chapters
                ->pluck('lessons')
                ->flatten()
                ->filter(fn ($lesson) => ($lesson->status ?? 'draft') === 'published')
                ->values();

            if ($this->relationLoaded('progresses')) {
                $lastProgress = $this->progresses
                    ->where('user_id', $user->id)
                    ->sortByDesc('updated_at')
                    ->first();

                if ($lastProgress) {
                    $matched = $publishedLessons->firstWhere('id', $lastProgress->lesson_id);
                    if ($matched) {
                        return $this->lastLessonCache[$user->id] = $matched;
                    }
                }
            }

            return $this->lastLessonCache[$user->id] = $publishedLessons->first();
        }

        // Find most recently updated progress for this user in this course
        $lastProgress = $this->progresses()
            ->where('user_id', $user->id)
            ->latest('updated_at')
            ->first();

        if ($lastProgress && $lastProgress->lesson) {
            return $this->lastLessonCache[$user->id] = $lastProgress->lesson;
        }

        // If no progress, find first lesson
        return $this->lastLessonCache[$user->id] = $this->chapters()->first()?->lessons()->where('status', 'published')->first();
    }

    /**
     * Tính chỉ số Điểm tương tác video tách riêng khỏi Tiến độ xem (%).
     *
     * @return array{total: int, answered: int, correct: int, score_10: ?float, accuracy_percent: ?int}
     */
    public function videoInteractionStatsFor(?User $user): array
    {
        if (! $user) {
            return ['total' => 0, 'answered' => 0, 'correct' => 0, 'score_10' => null, 'accuracy_percent' => null];
        }

        if (array_key_exists($user->id, $this->videoQuizStatsCache)) {
            return $this->videoQuizStatsCache[$user->id];
        }

        if ($this->relationLoaded('videoInteractions')) {
            $interactions = $this->videoInteractions;
            $total = $interactions->count();
            if ($total === 0) {
                return $this->videoQuizStatsCache[$user->id] = [
                    'total' => 0,
                    'answered' => 0,
                    'correct' => 0,
                    'score_10' => null,
                    'accuracy_percent' => null,
                ];
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

            return $this->videoQuizStatsCache[$user->id] = [
                'total' => $total,
                'answered' => $answered,
                'correct' => $correct,
                'score_10' => round($correct * 10 / $total, 1),
                'accuracy_percent' => (int) round($correct * 100 / $total),
            ];
        }

        $interactionIds = $this->videoInteractions()->pluck('id');
        $total = $interactionIds->count();
        if ($total === 0) {
            return $this->videoQuizStatsCache[$user->id] = [
                'total' => 0,
                'answered' => 0,
                'correct' => 0,
                'score_10' => null,
                'accuracy_percent' => null,
            ];
        }

        $responses = VideoInteractionResponse::where('user_id', $user->id)
            ->whereIn('video_interaction_id', $interactionIds)
            ->get();

        $answered = $responses->count();
        $correct = $responses->where('is_correct', true)->count();

        return $this->videoQuizStatsCache[$user->id] = [
            'total' => $total,
            'answered' => $answered,
            'correct' => $correct,
            'score_10' => round($correct * 10 / $total, 1),
            'accuracy_percent' => (int) round($correct * 100 / $total),
        ];
    }
}
