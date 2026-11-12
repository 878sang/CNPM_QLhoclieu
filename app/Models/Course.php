<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use HasFactory;

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
        return $this->hasMany(Chapter::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
