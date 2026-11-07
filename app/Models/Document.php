<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    use HasFactory;

    protected $fillable = ['course_id', 'lesson_id', 'title', 'file_path', 'original_name', 'mime_type', 'size', 'allow_view', 'allow_download'];

    protected function casts(): array
    {
        return ['allow_view' => 'boolean', 'allow_download' => 'boolean'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
