<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TeacherSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $teacherRole = Role::where('slug', 'teacher')->first();

            if (!$teacherRole) {
                $this->command->warn('Role teacher não encontrada. Execute as migrations primeiro.');
                return;
            }

            $teacher = User::create([
                'name' => 'Gabriel',
                'email' => 'gabriel@teacher.com',
                'password' => Hash::make('password'),
            ]);

            $teacher->roles()->attach($teacherRole->id);

            $this->command->info('Professor Gabriel criado com sucesso!');
            $this->command->info('Email: gabriel@teacher.com');
            $this->command->info('Senha: password');
        });
    }
}