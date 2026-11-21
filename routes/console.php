<?php

use App\Models\Course;
use App\Models\Lesson;
use App\Services\GeminiTutorService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('ai:prepare-lesson {courseId} {lessonId}', function (int $courseId, int $lessonId, GeminiTutorService $tutorService) {
    if (! $tutorService->isConfigured()) {
        return 0;
    }

    $course = Course::with('subject')->find($courseId);
    $lesson = Lesson::with([
        'chapter',
        'documents' => fn ($q) => $q->approved(),
    ])->find($lessonId);

    if (! $course || ! $lesson || ! $lesson->chapter || $lesson->chapter->course_id !== $course->id) {
        return 0;
    }

    if ($tutorService->hasFreshContext($lesson)) {
        return 0;
    }

    $lock = Cache::lock("ai_prepare_lesson_{$lesson->id}", 180);
    if (! $lock->get()) {
        return 0;
    }

    try {
        $result = $tutorService->prepareLessonContext($course, $lesson);
        if (($result['status'] ?? '') !== 'ready') {
            Cache::put("ai_prepare_cooldown_{$lesson->id}", true, 120);
        } else {
            Cache::forget("ai_prepare_cooldown_{$lesson->id}");
        }
    } finally {
        $lock->release();
    }

    return 0;
})->purpose('Đọc ngầm video và tài liệu của một bài học cho Trợ lý AI trong tiến trình nền độc lập');
