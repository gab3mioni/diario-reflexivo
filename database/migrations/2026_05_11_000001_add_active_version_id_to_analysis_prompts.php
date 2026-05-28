<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_prompts', function (Blueprint $table) {
            $table->foreignId('active_version_id')
                ->nullable()
                ->after('description')
                ->constrained('analysis_prompt_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('analysis_prompts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('active_version_id');
        });
    }
};
