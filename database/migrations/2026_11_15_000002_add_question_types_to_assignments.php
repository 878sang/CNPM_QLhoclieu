<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table): void {
            if (! Schema::hasColumn('assignments', 'question_types')) {
                $table->json('question_types')->nullable()->after('type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table): void {
            if (Schema::hasColumn('assignments', 'question_types')) {
                $table->dropColumn('question_types');
            }
        });
    }
};
