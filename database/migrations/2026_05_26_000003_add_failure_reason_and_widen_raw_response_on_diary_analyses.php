<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona classificação estruturada de falha e amplia raw_response para acomodar
 * o overhead da criptografia em nível de aplicação (cast encrypted).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diary_analyses', function (Blueprint $table) {
            $table->string('failure_reason', 40)->nullable()->after('error_message');
        });

        DB::statement('ALTER TABLE diary_analyses MODIFY raw_response MEDIUMTEXT NULL');
    }

    public function down(): void
    {
        Schema::table('diary_analyses', function (Blueprint $table) {
            $table->dropColumn('failure_reason');
        });

        DB::statement('ALTER TABLE diary_analyses MODIFY raw_response TEXT NULL');
    }
};
