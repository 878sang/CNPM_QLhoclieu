<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('lesson_interactions')->cascadeOnDelete();
            $table->string('type', 32)->default('question'); // question, comment, discussion
            $table->text('content');
            $table->unsignedInteger('video_timestamp_seconds')->nullable();
            $table->string('status', 32)->default('open'); // open, resolved, hidden
            $table->boolean('is_pinned')->default(false);
            $table->unsignedInteger('likes_count')->default(0);
            $table->timestamps();

            $table->index(['course_id', 'lesson_id', 'parent_id']);
            $table->index(['status', 'is_pinned']);
        });

        Schema::create('lesson_interaction_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_interaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['lesson_interaction_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_interaction_likes');
        Schema::dropIfExists('lesson_interactions');
    }
};
