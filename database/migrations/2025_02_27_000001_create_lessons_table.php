<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('scheduled_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['subject_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
