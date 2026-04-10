<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_responses', function (Blueprint $table) {
            $table->string('chat_state', 20)->default('idle')->after('awaiting_final_check');
            $table->timestamp('chat_state_since')->nullable()->after('chat_state');
            $table->index('chat_state');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_responses', function (Blueprint $table) {
            $table->dropIndex(['chat_state']);
            $table->dropColumn(['chat_state', 'chat_state_since']);
        });
    }
};
