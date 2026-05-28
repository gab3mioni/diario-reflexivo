<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prompt_version_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_prompt_id')
                ->constrained('analysis_prompts')
                ->cascadeOnDelete();
            $table->foreignId('previous_version_id')
                ->nullable()
                ->constrained('analysis_prompt_versions')
                ->nullOnDelete();
            $table->foreignId('new_version_id')
                ->nullable()
                ->constrained('analysis_prompt_versions')
                ->nullOnDelete();
            $table->foreignId('actor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['analysis_prompt_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompt_version_audits');
    }
};
