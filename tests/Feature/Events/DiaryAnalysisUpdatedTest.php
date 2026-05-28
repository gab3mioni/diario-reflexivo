<?php

use App\Events\DiaryAnalysisUpdated;
use App\Jobs\AnalyzeDiaryResponse;
use App\Models\AiProviderConfig;
use App\Models\AnalysisPromptVersion;
use App\Models\DiaryAnalysis;
use App\Models\Lesson;
use App\Models\LessonResponse;
use App\Models\Subject;
use App\Models\User;
use App\Services\Analysis\AnalysisResultValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('broadcasts DiaryAnalysisUpdated when the job completes successfully', function () {
    Event::fake([DiaryAnalysisUpdated::class]);

    $validResult = json_encode([
        'resumo' => 'O aluno demonstrou compreensão adequada do conteúdo da aula.',
        'indicadores' => [
            'compreensao' => 4,
            'engajamento' => 3,
            'pensamento_critico' => 3,
            'clareza_expressao' => 4,
            'reflexao_pessoal' => 3,
        ],
        'pontos_fortes' => ['Boa escrita'],
        'pontos_atencao' => [],
        'sugestoes_acao' => ['Sugerir leitura complementar'],
    ]);

    // Fake HTTP so no real LLM call is made. The factory creates an 'openai'
    // provider, so we fake the OpenAI chat-completions endpoint.
    Http::fake([
        '*' => Http::response([
            'choices' => [
                ['message' => ['content' => $validResult]],
            ],
        ], 200),
    ]);

    $teacher = User::factory()->create();
    $student = User::factory()->create();
    $subject = Subject::factory()->create(['teacher_id' => $teacher->id]);
    $lesson = Lesson::factory()->create(['subject_id' => $subject->id]);
    $response = LessonResponse::factory()->create([
        'lesson_id' => $lesson->id,
        'student_id' => $student->id,
        'content' => 'lorem ipsum',
        'submitted_at' => now(),
    ]);

    $providerConfig = AiProviderConfig::factory()->create();
    // The migration seeds the 'diary-analysis' prompt and its first version;
    // use them directly to avoid unique-constraint conflicts.
    $version = AnalysisPromptVersion::first();

    $analysis = DiaryAnalysis::create([
        'lesson_response_id' => $response->id,
        'prompt_version_id' => $version->id,
        'ai_provider_config_id' => $providerConfig->id,
        'status' => 'pending',
    ]);

    (new AnalyzeDiaryResponse($analysis))->handle(new AnalysisResultValidator);

    Event::assertDispatched(
        DiaryAnalysisUpdated::class,
        fn (DiaryAnalysisUpdated $e) => $e->analysis->id === $analysis->id
            && $e->analysis->status === 'completed',
    );
});

it('broadcasts DiaryAnalysisUpdated when the job fails', function () {
    Event::fake([DiaryAnalysisUpdated::class]);

    $analysis = DiaryAnalysis::factory()->create(['status' => 'pending']);

    (new AnalyzeDiaryResponse($analysis))->failed(new \RuntimeException('boom'));

    Event::assertDispatched(
        DiaryAnalysisUpdated::class,
        fn (DiaryAnalysisUpdated $e) => $e->analysis->id === $analysis->id
            && $e->analysis->status === 'failed',
    );
});
