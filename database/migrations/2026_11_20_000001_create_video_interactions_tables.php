<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('timestamp_seconds');
            $table->string('question_type', 32)->default('multiple_choice'); // multiple_choice, true_false, short_answer
            $table->text('question');
            $table->json('options')->nullable();
            $table->string('correct_answer');
            $table->text('explanation')->nullable();
            $table->boolean('is_required')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['document_id', 'timestamp_seconds']);
            $table->index(['course_id', 'lesson_id']);
        });

        Schema::create('video_interaction_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_interaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('selected_answer');
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('attempts')->default(1);
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->unique(['video_interaction_id', 'user_id']);
            $table->index(['user_id', 'is_correct']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_interaction_responses');
        Schema::dropIfExists('video_interactions');
    }
};
