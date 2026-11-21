<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('documents') && ! Schema::hasColumn('documents', 'gemini_file_uri')) {
            Schema::table('documents', function (Blueprint $table): void {
                $table->string('gemini_file_uri')->nullable()->after('converted_pdf_path');
                $table->string('gemini_file_name')->nullable()->after('gemini_file_uri');
                $table->timestamp('gemini_file_expires_at')->nullable()->after('gemini_file_name');
            });
        }

        Schema::create('ai_tutor_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16); // user, assistant
            $table->longText('content');
            $table->unsignedInteger('video_timestamp_seconds')->nullable();
            $table->string('gemini_interaction_id')->nullable();
            $table->json('sources_used')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'lesson_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_tutor_messages');

        if (Schema::hasTable('documents') && Schema::hasColumn('documents', 'gemini_file_uri')) {
            Schema::table('documents', function (Blueprint $table): void {
                $table->dropColumn(['gemini_file_uri', 'gemini_file_name', 'gemini_file_expires_at']);
            });
        }
    }
};
