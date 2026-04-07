<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CourseSeeder::class,          // 1. Cria o curso ADS
            TeacherSeeder::class,         // 2. Cria o professor Gabriel
            SubjectSeeder::class,         // 3. Cria as 4 matérias
            StudentSeeder::class,         // 4. Cria 160 alunos e matricula nas matérias
            QuestionScriptSeeder::class,  // 5. Cria o roteiro padrão de perguntas
        ]);
    }
}
