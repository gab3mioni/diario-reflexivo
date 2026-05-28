<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diary_analysis_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diary_analysis_id')
                ->constrained('diary_analyses')
                ->cascadeOnDelete();
            $table->foreignId('lesson_response_id')
                ->constrained('lesson_responses')
                ->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('severity', 8);
            $table->string('title', 160);
            $table->string('detail', 500)->nullable();
            $table->string('evidence', 500)->nullable();
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->string('status', 16)->default('pending');
            $table->text('teacher_note')->nullable();
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['lesson_response_id', 'status']);
            $table->index(['diary_analysis_id', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diary_analysis_alerts');
    }
};
