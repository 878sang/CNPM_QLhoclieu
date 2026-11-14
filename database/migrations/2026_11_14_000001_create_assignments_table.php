<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type', 20)->default('text');
            $table->unsignedSmallInteger('question_count')->default(10);
            $table->unsignedSmallInteger('time_limit_minutes')->nullable();
            $table->datetime('deadline')->nullable();
            $table->json('answer_key')->nullable(); // {"1":"18432","2":"303"} hoặc {"1":"A","2":"B"}
            $table->string('file_path')->nullable();      // file đề bài (giáo viên upload)
            $table->string('answer_file_path')->nullable(); // file đáp án (chỉ giáo viên xem)
            $table->string('original_name')->nullable();
            $table->string('answer_original_name')->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamps();
        });

        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('answers')->nullable(); // {"1":"18432","2":"A"}
            $table->string('file_path')->nullable();     // file bài làm học viên
            $table->string('original_name')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->decimal('max_score', 5, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamps();

            $table->unique(['assignment_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
        Schema::dropIfExists('assignments');
    }
};
