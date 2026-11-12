<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('approval_status', 20)->default('pending')->after('allow_download');
            $table->text('rejection_reason')->nullable()->after('approval_status');
            $table->foreignId('reviewed_by')->nullable()->after('rejection_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->string('pdf_path')->nullable()->after('reviewed_at');
            $table->string('conversion_status', 20)->default('not_needed')->after('pdf_path');
        });

        // Tài liệu có trước khi có kiểm duyệt được coi là đã duyệt để học viên không mất quyền truy cập.
        DB::table('documents')->update(['approval_status' => 'approved', 'reviewed_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['approval_status', 'rejection_reason', 'reviewed_at', 'pdf_path', 'conversion_status']);
        });
    }
};
