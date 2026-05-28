<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_responses', function (Blueprint $table) {
            $table->string('pending_confirm_exit_node', 64)
                ->nullable()
                ->after('low_engagement_streak');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_responses', function (Blueprint $table) {
            $table->dropColumn('pending_confirm_exit_node');
        });
    }
};
