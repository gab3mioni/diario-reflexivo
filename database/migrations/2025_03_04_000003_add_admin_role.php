<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!DB::table('roles')->where('slug', 'admin')->exists()) {
            DB::table('roles')->insert([
                'name' => 'admin',
                'slug' => 'admin',
                'display_name' => 'Administrador',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('roles')->where('slug', 'admin')->delete();
    }
};
