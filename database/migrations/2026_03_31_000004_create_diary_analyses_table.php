<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('diary_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_response_id')->constrained('lesson_responses')->cascadeOnDelete();
            $table->foreignId('prompt_version_id')->constrained('analysis_prompt_versions')->restrictOnDelete();
            $table->foreignId('ai_provider_config_id')->constrained('ai_provider_configs')->restrictOnDelete();
            $table->string('status', 20)->default('pending'); // pending, completed, failed, approved, rejected
            $table->json('result')->nullable();
            $table->text('raw_response')->nullable();
            $table->text('error_message')->nullable();
            $table->text('teacher_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['lesson_response_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diary_analyses');
    }
};
