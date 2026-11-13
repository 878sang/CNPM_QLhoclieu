<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('type')->default('file')->after('lesson_id');
            $table->text('url')->nullable()->after('type');
            $table->string('file_path')->nullable()->change();
            $table->string('original_name')->nullable()->change();
            $table->string('mime_type')->nullable()->change();
            $table->unsignedBigInteger('size')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['type', 'url']);
            $table->string('file_path')->nullable(false)->change();
            $table->string('original_name')->nullable(false)->change();
            $table->string('mime_type')->nullable(false)->change();
            $table->unsignedBigInteger('size')->nullable(false)->change();
        });
    }
};
