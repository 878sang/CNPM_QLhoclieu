<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Document> */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'type' => 'file',
            'title' => fake()->sentence(3),
            'file_path' => 'documents/'.fake()->uuid().'.pdf',
            'converted_pdf_path' => null,
            'original_name' => 'tai-lieu.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'allow_view' => true,
            'allow_download' => false,
            'approval_status' => Document::STATUS_APPROVED,
        ];
    }

    public function pending(): static
    {
        return $this->state(['approval_status' => Document::STATUS_PENDING]);
    }

    public function rejected(string $reason = 'Nội dung chưa phù hợp.'): static
    {
        return $this->state(['approval_status' => Document::STATUS_REJECTED, 'rejection_reason' => $reason]);
    public function video(string $url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'): static
    {
        return $this->state(fn () => [
            'type' => 'video',
            'url' => $url,
            'file_path' => null,
            'original_name' => null,
            'mime_type' => null,
            'size' => null,
        ]);
    }

    public function link(string $url = 'https://example.com/tai-lieu'): static
    {
        return $this->state(fn () => [
            'type' => 'link',
            'url' => $url,
            'file_path' => null,
            'original_name' => null,
            'mime_type' => null,
            'size' => null,
        ]);
    }
}
