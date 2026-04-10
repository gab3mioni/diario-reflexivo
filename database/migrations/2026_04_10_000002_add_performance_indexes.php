<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Índices de suporte a queries quentes identificadas na revisão de performance.
     *
     *  - response_alerts(lesson_response_id, read_at): consultas "alertas não lidos
     *    por resposta" (dashboard do professor) viram index-only scan.
     *  - lesson_responses(student_message_count): teste de cap global (>= 8)
     *    sobre a tabela inteira em relatórios; evita table scan.
     *  - diary_analyses(lesson_response_id, created_at): suporta a janela de 24h
     *    do DiaryAnalysisService::canRequestAnalysis.
     */
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
