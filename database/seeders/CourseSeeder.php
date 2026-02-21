<?php

namespace Database\Seeders;

use App\Models\Course;
use Illuminate\Database\Seeder;

class CourseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Course::create([
            'name' => 'Análise e Desenvolvimento de Sistemas',
            'slug' => 'ads',
            'is_active' => true,
        ]);
    }
}