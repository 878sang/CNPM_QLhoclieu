<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentProgress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DocumentProgress> */
class DocumentProgressFactory extends Factory
{
    protected $model = DocumentProgress::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'document_id' => Document::factory(),
            'position_seconds' => 0,
            'duration_seconds' => 0,
            'percent' => 0,
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(['percent' => 100, 'completed_at' => now()]);
    }
}
