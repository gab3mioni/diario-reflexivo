<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('analysis_prompt_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_prompt_id')->constrained('analysis_prompts')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->text('content');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['analysis_prompt_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_prompt_versions');
    }
};
