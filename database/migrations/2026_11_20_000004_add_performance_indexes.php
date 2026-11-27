<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $addIndex = function (string $tableName, array $columns, string $indexName): void {
            if (! Schema::hasTable($tableName)) {
                return;
            }
            foreach ($columns as $col) {
                if (! Schema::hasColumn($tableName, $col)) {
                    return;
                }
            }
            try {
                Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName): void {
                    $table->index($columns, $indexName);
                });
            } catch (Throwable) {
                // Bỏ qua nếu index đã tồn tại
            }
        };

        $addIndex('enrollments', ['course_id', 'status'], 'idx_enrollments_course_status');
        $addIndex('enrollments', ['user_id', 'status'], 'idx_enrollments_user_status');
        $addIndex('lessons', ['chapter_id', 'status'], 'idx_lessons_chapter_status');
        $addIndex('documents', ['course_id', 'approval_status'], 'idx_documents_course_approval');
        $addIndex('progress', ['user_id', 'course_id'], 'idx_progress_user_course');
        $addIndex('assignments', ['course_id', 'status'], 'idx_assignments_course_status');
    }

    public function down(): void
    {
        $dropIndex = function (string $tableName, string $indexName): void {
            if (! Schema::hasTable($tableName)) {
                return;
            }
            try {
                Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                    $table->dropIndex($indexName);
                });
            } catch (Throwable) {
            }
        };

        $dropIndex('enrollments', 'idx_enrollments_course_status');
        $dropIndex('enrollments', 'idx_enrollments_user_status');
        $dropIndex('lessons', 'idx_lessons_chapter_status');
        $dropIndex('documents', 'idx_documents_course_approval');
        $dropIndex('progress', 'idx_progress_user_course');
        $dropIndex('assignments', 'idx_assignments_course_status');
    }
};
