<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentProgress extends Model
{
    use HasFactory;

    /** Xem được từ mức này trở lên thì tính là đã hoàn thành. */
    public const COMPLETE_PERCENT = 90;

    protected $table = 'document_progress';

    protected $fillable = ['user_id', 'document_id', 'position_seconds', 'duration_seconds', 'percent', 'completed_at'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Ghi nhận tiến độ xem của học viên. Tiến độ chỉ tăng, không bị giảm khi học viên tua lại.
     */
    public static function record(User $user, Document $document, int $positionSeconds, int $durationSeconds): self
    {
        $progress = self::firstOrNew(['user_id' => $user->id, 'document_id' => $document->id]);

        $duration = max($durationSeconds, 0);
        $position = $duration > 0 ? min(max($positionSeconds, 0), $duration) : 0;
        $percent = $duration > 0 ? (int) floor($position * 100 / $duration) : 0;

        $progress->duration_seconds = $duration ?: $progress->duration_seconds;
        $progress->position_seconds = $position;
        $progress->percent = max($percent, (int) $progress->percent);

        if ($progress->percent >= self::COMPLETE_PERCENT) {
            $progress->completed_at ??= now();
        }

        $progress->save();

        return $progress;
    }

    /**
     * Tài liệu không phải video: mở xem là hoàn thành.
     */
    public static function markViewed(User $user, Document $document): self
    {
        $progress = self::firstOrNew(['user_id' => $user->id, 'document_id' => $document->id]);
        $progress->percent = 100;
        $progress->completed_at ??= now();
        $progress->save();

        return $progress;
    }
}
