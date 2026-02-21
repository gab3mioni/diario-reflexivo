<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $course = Course::where('slug', 'ads')->first();

        if (!$course) {
            $this->command->warn('Curso ADS não encontrado. Execute CourseSeeder primeiro.');
            return;
        }

        $teacher = \App\Models\User::where('email', 'gabriel@teacher.com')->first();

        if (!$teacher) {
            $this->command->warn('Professor Gabriel não encontrado. Execute TeacherSeeder primeiro.');
            return;
        }

        $subjects = [
            [
                'name' => 'Linguagem de Programação',
                'slug' => 'linguagem-de-programacao',
            ],
            [
                'name' => 'Programação Orientada a Objetos',
                'slug' => 'programacao-orientada-a-objetos',
            ],
            [
                'name' => 'Estrutura de Dados',
                'slug' => 'estrutura-de-dados',
            ],
            [
                'name' => 'Inteligência Artificial',
                'slug' => 'inteligencia-artificial',
            ],
        ];

        foreach ($subjects as $subject) {
            Subject::create([
                'name' => $subject['name'],
                'slug' => $subject['slug'],
                'course_id' => $course->id,
                'teacher_id' => $teacher->id,
                'is_active' => true,
            ]);
        }
    }
}