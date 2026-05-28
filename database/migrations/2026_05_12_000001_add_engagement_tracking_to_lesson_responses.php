<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_responses', function (Blueprint $table) {
            $table->unsignedInteger('low_engagement_streak')
                ->default(0)
                ->after('free_talk_turn_count');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_responses', function (Blueprint $table) {
            $table->dropColumn('low_engagement_streak');
        });
    }
};
