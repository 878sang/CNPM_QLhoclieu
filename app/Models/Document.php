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
        'course_id',
        'lesson_id',
        'type',
        'url',
        'title',
        'file_path',
        'converted_pdf_path',
        'original_name',
        'mime_type',
        'size',
        'allow_view',
        'allow_download',
        'status',
        'approval_status',
        'reviewed_by',
        'reviewed_at',
        'pdf_path',
        'conversion_status',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'allow_view' => 'boolean',
            'allow_download' => 'boolean',
            'size' => 'integer',
            'reviewed_at' => 'datetime',
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
        $query->where(function (Builder $query): void {
            $query->where('approval_status', self::STATUS_APPROVED)
                ->where(function (Builder $query): void {
                    $query->whereNull('status')->orWhere('status', self::STATUS_APPROVED);
                })
                ->orWhere(function (Builder $query): void {
                    $query->whereNull('approval_status')->where('status', self::STATUS_APPROVED);
                });
        });
    }

    /**
     * Tài liệu học viên có thể xem và được tính vào tiến độ: đã duyệt và cho phép xem trực tuyến.
     */
    public function scopeTrackable(Builder $query): void
    {
        $query->approved()->where('allow_view', true);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MaterialLog::class);
    }

    public function isLink(): bool
    {
        return $this->type === 'link';
    }

    public function isVideo(): bool
    {
        if ($this->type === 'video') {
            return true;
        }

        if ($this->mime_type && str_starts_with($this->mime_type, 'video/')) {
            return true;
        }

        $extension = strtolower(pathinfo($this->original_name ?? '', PATHINFO_EXTENSION));

        return in_array($extension, ['mp4', 'webm', 'ogg', 'mkv', 'mov']);
    }

    public function isPdf(): bool
    {
        if ($this->mime_type === 'application/pdf') {
            return true;
        }

        return strtolower(pathinfo($this->original_name ?? '', PATHINFO_EXTENSION)) === 'pdf';
    }

    public function isOffice(): bool
    {
        $extension = strtolower(pathinfo($this->original_name ?? '', PATHINFO_EXTENSION));

        return in_array($extension, ['doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx']);
    }

    public function isWord(): bool
    {
        $extension = strtolower(pathinfo($this->original_name ?? '', PATHINFO_EXTENSION));

        return in_array($extension, ['doc', 'docx']);
    }

    public function isPowerPoint(): bool
    {
        $extension = strtolower(pathinfo($this->original_name ?? '', PATHINFO_EXTENSION));

        return in_array($extension, ['ppt', 'pptx']);
    }

    public function isConvertibleOffice(): bool
    {
        return $this->isWord() || $this->isPowerPoint();
    }

    public function hasConvertedPdf(): bool
    {
        return ! empty($this->getAttribute('converted_pdf_path'));
    }

    public function isApproved(): bool
    {
        $legacyStatus = $this->getAttribute('status');
        if (in_array($legacyStatus, [self::STATUS_PENDING, self::STATUS_REJECTED], true)) {
            return false;
        }

        return ($this->approval_status ?? $legacyStatus) === self::STATUS_APPROVED;
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

    public function isPending(): bool
    {
        return ($this->getAttribute('status') ?? $this->approval_status) === self::STATUS_PENDING;
    }

    public function isRejected(): bool
    {
        return ($this->getAttribute('status') ?? $this->approval_status) === self::STATUS_REJECTED;
    }

    public function statusLabel(): string
    {
        return match ($this->getAttribute('status')) {
            'pending' => 'Chờ duyệt',
            'rejected' => 'Bị từ chối',
            default => 'Đã duyệt',
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->getAttribute('status')) {
            'pending' => 'bg-warning text-dark',
            'rejected' => 'bg-danger text-white',
            default => 'bg-success text-white',
        };
    }

    public function isYouTube(): bool
    {
        if (! $this->url) {
            return false;
        }

        return (bool) preg_match('/(youtube\.com|youtu\.be)/i', $this->url);
    }

    public function isGoogleDrive(): bool
    {
        if (! $this->url) {
            return false;
        }

        return (bool) preg_match('/drive\.google\.com/i', $this->url);
    }

    public function getEmbedUrlAttribute(): ?string
    {
        if (! $this->url) {
            return null;
        }

        // YouTube
        if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $this->url, $matches)) {
            return 'https://www.youtube.com/embed/'.$matches[1];
        }

        // Google Drive preview
        if (preg_match('/drive\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)/', $this->url, $matches)) {
            return 'https://drive.google.com/file/d/'.$matches[1].'/preview';
        }

        return $this->url;
    }

    public function formattedSize(): string
    {
        if (! $this->size) {
            return '—';
        }

        if ($this->size >= 1048576) {
            return number_format($this->size / 1048576, 1, ',', '.').' MB';
        }

        return number_format($this->size / 1024, 0, ',', '.').' KB';
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'video' => 'Video bài giảng',
            'link' => 'Liên kết ngoài',
            default => 'Tài liệu tệp',
        };
    }

    public function typeIcon(): string
    {
        if ($this->isVideo()) {
            return 'bi-play-circle-fill text-danger';
        }

        if ($this->isLink()) {
            return 'bi-link-45deg text-info';
        }

        if ($this->isPdf()) {
            return 'bi-file-earmark-pdf-fill text-danger';
        }

        $extension = strtolower(pathinfo($this->original_name ?? '', PATHINFO_EXTENSION));
        if (in_array($extension, ['doc', 'docx'])) {
            return 'bi-file-earmark-word-fill text-primary';
        }
        if (in_array($extension, ['ppt', 'pptx'])) {
            return 'bi-file-earmark-ppt-fill text-warning';
        }
        if (in_array($extension, ['xls', 'xlsx'])) {
            return 'bi-file-earmark-excel-fill text-success';
        }

        return 'bi-file-earmark-text-fill text-secondary';
    }
}
