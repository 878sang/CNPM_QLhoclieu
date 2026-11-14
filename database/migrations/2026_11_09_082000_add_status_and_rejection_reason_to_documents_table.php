<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('documents', 'status')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->string('status')->default('approved')->after('allow_download')->index();
            });
        }

        if (! Schema::hasColumn('documents', 'rejection_reason')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->text('rejection_reason')->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        $columns = array_filter(['status', 'rejection_reason'], fn (string $column): bool => Schema::hasColumn('documents', $column));
        if ($columns !== []) {
            Schema::table('documents', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
