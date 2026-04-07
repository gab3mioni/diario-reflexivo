<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_response_id')->constrained()->onDelete('cascade');
            $table->string('node_id')->nullable();
            $table->enum('role', ['bot', 'student']);
            $table->text('content');
            $table->timestamps();

            $table->index('lesson_response_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
