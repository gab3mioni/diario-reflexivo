<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('response_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_response_id')
                ->constrained('lesson_responses')
                ->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('severity', 8);
            $table->string('reason', 500)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index('lesson_response_id');
            $table->index('read_at');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('response_alerts');
    }
};
