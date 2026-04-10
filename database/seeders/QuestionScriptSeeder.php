<?php

namespace Database\Seeders;

use App\Models\QuestionScript;
use Illuminate\Database\Seeder;

class QuestionScriptSeeder extends Seeder
{
    public function run(): void
    {
        // Reset to a single canonical default so reseeds stay deterministic.
        QuestionScript::query()->delete();

        QuestionScript::create([
            'name' => 'Roteiro Padrão',
            'description' => 'Roteiro padrão de reflexão com checagem de presença, ramificação por texto livre e espaços de conversa livre.',
            'is_active' => true,
            'nodes' => [
                [
                    'id' => 'node-start',
                    'type' => 'start',
                    'position' => ['x' => 300, 'y' => 0],
                    'data' => [
                        'message' => 'Olá! Vamos conversar um pouco sobre a aula de hoje. 😊',
                    ],
                ],
                [
                    'id' => 'node-presence',
                    'type' => 'question',
                    'position' => ['x' => 300, 'y' => 140],
                    'data' => [
                        'message' => 'Antes de tudo: você esteve presente na aula de hoje?',
                        'collection_type' => 'option',
                        'options' => [
                            ['label' => 'Sim'],
                            ['label' => 'Não'],
                        ],
                    ],
                ],
                [
                    'id' => 'node-ft-absence',
                    'type' => 'free_talk',
                    'position' => ['x' => 80, 'y' => 300],
                    'data' => [
                        'message' => 'Sinto que você não pôde estar hoje. Quer me contar o que aconteceu ou como está se sentindo em relação à aula?',
                        'closing_message' => 'Obrigado por compartilhar. Fico por aqui se precisar.',
                        'max_turns' => 3,
                        'alert' => [
                            'type' => 'absence',
                            'severity' => 'medium',
                            'reason' => 'Aluno marcou falta na aula.',
                        ],
                    ],
                ],
                [
                    'id' => 'node-q-feeling',
                    'type' => 'question',
                    'position' => ['x' => 520, 'y' => 300],
                    'data' => [
                        'message' => 'Como você se sentiu durante a aula de hoje?',
                        'collection_type' => 'free_text',
                    ],
                ],
                [
                    'id' => 'node-q-learning',
                    'type' => 'question',
                    'position' => ['x' => 520, 'y' => 460],
                    'data' => [
                        'message' => 'O que foi mais marcante pra você nessa aula? Algo chamou sua atenção ou gerou alguma dúvida?',
                        'collection_type' => 'free_text',
                    ],
                ],
                [
                    'id' => 'node-ft-doubt',
                    'type' => 'free_talk',
                    'position' => ['x' => 760, 'y' => 620],
                    'data' => [
                        'message' => 'Parece que ficou alguma dificuldade. Pode me contar mais sobre onde você travou ou o que não ficou claro?',
                        'closing_message' => 'Obrigado por compartilhar. Vou registrar isso com cuidado.',
                        'max_turns' => 3,
                    ],
                ],
                [
                    'id' => 'node-q-apply',
                    'type' => 'question',
                    'position' => ['x' => 520, 'y' => 780],
                    'data' => [
                        'message' => 'Como você imagina aplicar o que aprendeu hoje no seu dia a dia ou em projetos futuros?',
                        'collection_type' => 'free_text',
                    ],
                ],
                [
                    'id' => 'node-end',
                    'type' => 'end',
                    'position' => ['x' => 300, 'y' => 940],
                    'data' => [
                        'message' => 'Obrigado pela sua reflexão! Seu diário foi salvo com sucesso. 🎉',
                    ],
                ],
            ],
            'edges' => [
                [
                    'id' => 'e-start-presence',
                    'source' => 'node-start',
                    'target' => 'node-presence',
                    'is_default' => true,
                ],
                [
                    'id' => 'e-presence-yes',
                    'source' => 'node-presence',
                    'target' => 'node-q-feeling',
                    'is_default' => true,
                    'condition' => ['description' => 'Sim'],
                ],
                [
                    'id' => 'e-presence-no',
                    'source' => 'node-presence',
                    'target' => 'node-ft-absence',
                    'is_default' => false,
                    'condition' => ['description' => 'Não'],
                ],
                [
                    'id' => 'e-ft-absence-end',
                    'source' => 'node-ft-absence',
                    'target' => 'node-end',
                    'is_default' => true,
                ],
                [
                    'id' => 'e-feeling-learning',
                    'source' => 'node-q-feeling',
                    'target' => 'node-q-learning',
                    'is_default' => true,
                ],
                [
                    'id' => 'e-learning-apply',
                    'source' => 'node-q-learning',
                    'target' => 'node-q-apply',
                    'is_default' => true,
                ],
                [
                    'id' => 'e-learning-doubt',
                    'source' => 'node-q-learning',
                    'target' => 'node-ft-doubt',
                    'is_default' => false,
                    'condition' => [
                        'description' => 'O aluno demonstra dúvida, dificuldade de compreensão ou confusão sobre o conteúdo da aula.',
                    ],
                ],
                [
                    'id' => 'e-ft-doubt-apply',
                    'source' => 'node-ft-doubt',
                    'target' => 'node-q-apply',
                    'is_default' => true,
                ],
                [
                    'id' => 'e-apply-end',
                    'source' => 'node-q-apply',
                    'target' => 'node-end',
                    'is_default' => true,
                ],
            ],
        ]);
    }
}
