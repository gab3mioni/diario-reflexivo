<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_provider_configs', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('provider', 50); // openai, gemini, ollama
            $table->string('model', 100);
            $table->decimal('temperature', 3, 2)->default(0.70);
            $table->text('api_key')->nullable();
            $table->string('base_url', 500)->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_provider_configs');
    }
};
