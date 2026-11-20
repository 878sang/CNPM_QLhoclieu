<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoInteractionResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'video_interaction_id',
        'user_id',
        'selected_answer',
        'is_correct',
        'attempts',
        'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'attempts' => 'integer',
            'answered_at' => 'datetime',
        ];
    }

    public function interaction(): BelongsTo
    {
        return $this->belongsTo(VideoInteraction::class, 'video_interaction_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
