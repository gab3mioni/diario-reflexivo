<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->string('classifier_status', 24)->nullable()->after('content');
            $table->string('classifier_reason', 500)->nullable()->after('classifier_status');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn(['classifier_status', 'classifier_reason']);
        });
    }
};
