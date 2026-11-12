<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED];

    protected $fillable = [
        'course_id', 'lesson_id', 'title', 'file_path', 'original_name', 'mime_type', 'size', 'allow_view', 'allow_download',
        'approval_status', 'rejection_reason', 'reviewed_by', 'reviewed_at', 'pdf_path', 'conversion_status',
    ];

    protected function casts(): array
    {
        return ['allow_view' => 'boolean', 'allow_download' => 'boolean', 'reviewed_at' => 'datetime'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(DocumentProgress::class);
    }

    public function scopeApproved(Builder $query): void
    {
        $query->where('approval_status', self::STATUS_APPROVED);
    }

    /**
     * Tài liệu học viên có thể xem và được tính vào tiến độ: đã duyệt và cho phép xem trực tuyến.
     */
    public function scopeTrackable(Builder $query): void
    {
        $query->approved()->where('allow_view', true);
    }

    public function isVideo(): bool
    {
        return str_starts_with((string) $this->mime_type, 'video/');
    }

    public function isApproved(): bool
    {
        return $this->approval_status === self::STATUS_APPROVED;
    }

    /**
     * Tệp dùng để xem trực tuyến: bản PDF đã chuyển đổi nếu có, ngược lại là tệp gốc.
     *
     * @return array{path: string, mime: string, name: string}
     */
    public function viewableFile(): array
    {
        if ($this->pdf_path) {
            return ['path' => $this->pdf_path, 'mime' => 'application/pdf', 'name' => pathinfo($this->original_name, PATHINFO_FILENAME).'.pdf'];
        }

        return ['path' => $this->file_path, 'mime' => $this->mime_type, 'name' => $this->original_name];
    }
}
