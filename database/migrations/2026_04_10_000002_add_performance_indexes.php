<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('response_alerts', function (Blueprint $table) {
            $table->index(['lesson_response_id', 'read_at'], 'response_alerts_response_unread_idx');
        });

        Schema::table('lesson_responses', function (Blueprint $table) {
            $table->index('student_message_count', 'lesson_responses_msg_count_idx');
        });

        Schema::table('diary_analyses', function (Blueprint $table) {
            $table->index(['lesson_response_id', 'created_at'], 'diary_analyses_response_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('response_alerts', function (Blueprint $table) {
            $table->dropIndex('response_alerts_response_unread_idx');
        });

        Schema::table('lesson_responses', function (Blueprint $table) {
            $table->dropIndex('lesson_responses_msg_count_idx');
        });

        Schema::table('diary_analyses', function (Blueprint $table) {
            $table->dropIndex('diary_analyses_response_created_idx');
        });
    }
};
