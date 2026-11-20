<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table): void {
            $table->string('rejection_reason')->nullable()->after('status');
            $table->foreignId('reviewed_by')->nullable()->after('rejection_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });

        // MySQL enum columns need to explicitly include the new workflow states.
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::getConnection()->statement("ALTER TABLE assignments MODIFY status ENUM('draft', 'pending', 'published', 'rejected') NOT NULL DEFAULT 'draft'");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::getConnection()->statement("UPDATE assignments SET status = 'draft' WHERE status IN ('pending', 'rejected')");
            Schema::getConnection()->statement("ALTER TABLE assignments MODIFY status ENUM('draft', 'published') NOT NULL DEFAULT 'draft'");
        }

        Schema::table('assignments', function (Blueprint $table): void {
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['rejection_reason', 'reviewed_by', 'reviewed_at']);
        });
    }
};
