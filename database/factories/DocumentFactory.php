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
            'title' => fake()->sentence(3),
            'file_path' => 'documents/'.fake()->uuid().'.pdf',
            'original_name' => 'tai-lieu.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'allow_view' => true,
            'allow_download' => false,
        ];
    }
}
