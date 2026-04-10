<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('lesson_responses', function (Blueprint $table) {
            $table->unsignedInteger('student_message_count')->default(0)->after('content');
            $table->unsignedInteger('free_talk_turn_count')->default(0)->after('student_message_count');
            $table->boolean('awaiting_final_check')->default(false)->after('free_talk_turn_count');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_responses', function (Blueprint $table) {
            $table->dropColumn([
                'student_message_count',
                'free_talk_turn_count',
                'awaiting_final_check',
            ]);
        });
    }
};
