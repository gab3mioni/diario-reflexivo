<?php

namespace Database\Seeders;

use App\Models\QuestionScript;
use Illuminate\Database\Seeder;

class QuestionScriptSeeder extends Seeder
{
    public function run(): void
    {
        QuestionScript::create([
            'name' => 'Roteiro Padrão',
            'description' => 'Roteiro padrão de reflexão para diários de aula.',
            'is_active' => true,
            'nodes' => [
                [
                    'id' => 'node-start',
                    'type' => 'start',
                    'position' => ['x' => 250, 'y' => 0],
                    'data' => [
                        'message' => 'Olá! Vamos refletir sobre a aula de hoje. Vou te fazer algumas perguntas para ajudar na sua reflexão. 😊',
                    ],
                ],
                [
                    'id' => 'node-q1',
                    'type' => 'question',
                    'position' => ['x' => 250, 'y' => 150],
                    'data' => [
                        'message' => 'Como você se sentiu durante a aula de hoje?',
                    ],
                ],
                [
                    'id' => 'node-q2',
                    'type' => 'question',
                    'position' => ['x' => 250, 'y' => 300],
                    'data' => [
                        'message' => 'O que você aprendeu de mais importante nesta aula?',
                    ],
                ],
                [
                    'id' => 'node-q3',
                    'type' => 'question',
                    'position' => ['x' => 250, 'y' => 450],
                    'data' => [
                        'message' => 'Ficou com alguma dúvida ou dificuldade? Se sim, qual?',
                    ],
                ],
                [
                    'id' => 'node-q4',
                    'type' => 'question',
                    'position' => ['x' => 250, 'y' => 600],
                    'data' => [
                        'message' => 'Como você pode aplicar o que aprendeu hoje no seu dia a dia ou em projetos futuros?',
                    ],
                ],
                [
                    'id' => 'node-end',
                    'type' => 'end',
                    'position' => ['x' => 250, 'y' => 750],
                    'data' => [
                        'message' => 'Obrigado pela sua reflexão! Seu diário foi salvo com sucesso. 🎉',
                    ],
                ],
            ],
            'edges' => [
                ['id' => 'e-start-q1', 'source' => 'node-start', 'target' => 'node-q1'],
                ['id' => 'e-q1-q2', 'source' => 'node-q1', 'target' => 'node-q2'],
                ['id' => 'e-q2-q3', 'source' => 'node-q2', 'target' => 'node-q3'],
                ['id' => 'e-q3-q4', 'source' => 'node-q3', 'target' => 'node-q4'],
                ['id' => 'e-q4-end', 'source' => 'node-q4', 'target' => 'node-end'],
            ],
        ]);
    }
}
