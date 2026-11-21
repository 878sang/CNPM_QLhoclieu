<?php

namespace App\Models;

use App\Mail\AppNotificationMail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class AppNotification extends Model
{
    use HasFactory;

    public const TYPE_SYSTEM = 'system';

    public const TYPE_COURSE = 'course';

    public const TYPE_INTERACTION = 'interaction';

    public const TYPE_VIDEO_QUIZ = 'video_quiz';

    public const TYPE_ASSIGNMENT = 'assignment';

    public const TYPE_APPROVAL = 'approval';

    public const TYPES = [
        self::TYPE_SYSTEM => 'Hệ thống',
        self::TYPE_COURSE => 'Khóa học',
        self::TYPE_INTERACTION => 'Tương tác & Hỏi đáp',
        self::TYPE_VIDEO_QUIZ => 'Tương tác video',
        self::TYPE_ASSIGNMENT => 'Bài tập',
        self::TYPE_APPROVAL => 'Duyệt học liệu',
    ];

    protected $table = 'app_notifications';

    protected $fillable = [
        'user_id',
        'sender_id',
        'course_id',
        'title',
        'message',
        'type',
        'action_url',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at');
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function markAsRead(): void
    {
        if (! $this->read_at) {
            $this->update(['read_at' => now()]);
        }
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? 'Thông báo';
    }

    public function typeIcon(): string
    {
        return match ($this->type) {
            self::TYPE_COURSE => 'bi-journal-bookmark-fill text-primary',
            self::TYPE_INTERACTION => 'bi-chat-dots-fill text-info',
            self::TYPE_VIDEO_QUIZ => 'bi-play-btn-fill text-danger',
            self::TYPE_ASSIGNMENT => 'bi-journal-check text-success',
            self::TYPE_APPROVAL => 'bi-patch-check-fill text-warning',
            default => 'bi-megaphone-fill text-primary',
        };
    }

    public function typeBadgeClass(): string
    {
        return match ($this->type) {
            self::TYPE_COURSE => 'bg-primary-subtle text-primary border border-primary',
            self::TYPE_INTERACTION => 'bg-info-subtle text-info-emphasis border border-info',
            self::TYPE_VIDEO_QUIZ => 'bg-danger-subtle text-danger border border-danger',
            self::TYPE_ASSIGNMENT => 'bg-success-subtle text-success border border-success',
            self::TYPE_APPROVAL => 'bg-warning-subtle text-warning-emphasis border border-warning',
            default => 'bg-secondary-subtle text-secondary border border-secondary',
        };
    }

    /**
     * Gửi thông báo cho danh sách người dùng.
     *
     * @param  iterable<int|User>  $recipients
     */
    public static function notifyUsers(
        iterable $recipients,
        string $title,
        string $message,
        string $type = self::TYPE_SYSTEM,
        ?int $senderId = null,
        ?int $courseId = null,
        ?string $actionUrl = null
    ): int {
        $recipientIds = Collection::make($recipients)
            ->map(fn ($r) => $r instanceof User ? $r->id : (int) $r)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($recipientIds->isEmpty()) {
            return 0;
        }

        $now = now();
        $rows = $recipientIds->map(fn (int $userId) => [
            'user_id' => $userId,
            'sender_id' => $senderId,
            'course_id' => $courseId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'action_url' => $actionUrl,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        self::insert($rows);

        if (config('mail.notification_emails')) {
            User::whereIn('id', $recipientIds)->where('status', 'active')->each(function (User $recipient) use ($title, $message, $actionUrl): void {
                if (filled($recipient->email)) {
                    Mail::to($recipient->email)->queue((new AppNotificationMail($title, $message, $actionUrl))->afterCommit());
                }
            });
        }

        return count($rows);
    }

    /**
     * Gửi thông báo cho toàn bộ học viên đang học (active) của một khóa học.
     */
    public static function notifyCourseStudents(
        Course $course,
        string $title,
        string $message,
        string $type = self::TYPE_COURSE,
        ?int $senderId = null,
        ?string $actionUrl = null
    ): int {
        $studentIds = $course->enrollments()
            ->where('status', 'active')
            ->pluck('user_id');

        return self::notifyUsers(
            $studentIds,
            $title,
            $message,
            $type,
            $senderId,
            $course->id,
            $actionUrl
        );
    }
}
