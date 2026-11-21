<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->longText('ai_context_text')->nullable()->after('status');
            $table->string('ai_context_hash', 64)->nullable()->after('ai_context_text');
            $table->timestamp('ai_context_ready_at')->nullable()->after('ai_context_hash');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn(['ai_context_text', 'ai_context_hash', 'ai_context_ready_at']);
        });
    }
};
