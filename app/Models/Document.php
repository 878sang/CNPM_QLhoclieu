<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    use HasFactory;

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
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'allow_view' => 'boolean',
            'allow_download' => 'boolean',
            'size' => 'integer',
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
        return ($this->getAttribute('status') ?? 'approved') === 'approved';
    }

    public function isPending(): bool
    {
        return $this->getAttribute('status') === 'pending';
    }

    public function isRejected(): bool
    {
        return $this->getAttribute('status') === 'rejected';
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

    public function scopeApproved($query)
    {
        return $query->where(function ($q) {
            $q->where('status', 'approved')->orWhereNull('status');
        });
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
