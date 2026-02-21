<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class StudentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $studentRole = Role::where('slug', 'student')->first();

            if (!$studentRole) {
                $this->command->warn('Role student não encontrada. Execute as migrations primeiro.');
                return;
            }

            $subjects = Subject::with('course')->get();

            if ($subjects->count() !== 4) {
                $this->command->warn('É necessário ter exatamente 4 matérias criadas. Execute SubjectSeeder primeiro.');
                return;
            }

            $totalStudents = 160;
            $studentsPerSubject = 40;

            $faker = Faker::create('pt_BR');
            $students = [];
            $usedEmails = [];

            $this->command->info('Criando '.$totalStudents.' alunos com nomes realistas...');

            for ($i = 1; $i <= $totalStudents; $i++) {
                $name = $faker->name();
                $email = $faker->unique()->safeEmail();
                
                while (in_array($email, $usedEmails)) {
                    $email = $faker->unique()->safeEmail();
                }
                $usedEmails[] = $email;

                $students[] = [
                    'name' => $name,
                    'email' => $email,
                    'password' => bcrypt('password'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            User::insert($students);

            $allStudents = User::whereDoesntHave('roles')
                ->orderBy('id')
                ->take($totalStudents)
                ->get();

            foreach ($allStudents as $student) {
                $student->roles()->attach($studentRole->id);
            }

            $currentStudentIndex = 0;

            foreach ($subjects as $subject) {
                $this->command->info("Matriculando {$studentsPerSubject} alunos em: {$subject->name}");

                $subjectStudents = $allStudents->slice($currentStudentIndex, $studentsPerSubject);

                foreach ($subjectStudents as $student) {
                    $subject->students()->attach($student->id);
                }

                $currentStudentIndex += $studentsPerSubject;
            }

            $this->command->info('✅ '.$totalStudents.' alunos criados com dados realistas!');
            $this->command->info('✅ Cada matéria tem exatamente '.$studentsPerSubject.' alunos matriculados.');
            $this->command->info('✅ Senha padrão para todos os alunos: password');
        });
    }
}