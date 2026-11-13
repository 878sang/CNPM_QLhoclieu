<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Document;
use App\Models\MaterialLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MaterialLog> */
class MaterialLogFactory extends Factory
{
    protected $model = MaterialLog::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'course_id' => Course::factory(),
            'document_id' => Document::factory(),
            'action' => 'view',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 FakeBrowser',
        ];
    }

    public function download(): static
    {
        return $this->state(fn () => [
            'action' => 'download',
        ]);
    }
}
