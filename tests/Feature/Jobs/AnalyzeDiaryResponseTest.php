<?php

use App\Jobs\AnalyzeDiaryResponse;
use App\Models\AiProviderConfig;
use App\Models\AnalysisPromptVersion;
use App\Models\DiaryAnalysis;
use App\Models\DiaryAnalysisAlert;
use App\Models\LessonResponse;
use App\Services\Analysis\AnalysisResultValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function fakeDiaryProvider(mixed $content, int $status = 200): void
{
    $body = is_string($content)
        ? ['choices' => [['message' => ['content' => $content]]]]
        : $content;

    Http::fake(['*' => Http::response($body, $status)]);
}

function makeDiaryAnalysis(string $content = 'conteúdo da resposta do aluno'): DiaryAnalysis
{
    $response = LessonResponse::factory()->create(['content' => $content]);

    return DiaryAnalysis::create([
        'lesson_response_id' => $response->id,
        'prompt_version_id' => AnalysisPromptVersion::first()->id,
        'ai_provider_config_id' => AiProviderConfig::factory()->create()->id,
        'status' => DiaryAnalysis::STATUS_PENDING,
    ]);
}

function runDiaryJob(DiaryAnalysis $analysis): void
{
    (new AnalyzeDiaryResponse($analysis))->handle(new AnalysisResultValidator);
}

function diaryValidPayload(array $overrides = []): string
{
    return json_encode(array_merge([
        'resumo' => 'O aluno demonstrou compreensão adequada e reflexão pessoal.',
        'indicadores' => [
            'compreensao' => 4,
            'engajamento' => 3,
            'pensamento_critico' => 3,
            'clareza_expressao' => 4,
            'reflexao_pessoal' => 3,
        ],
        'pontos_fortes' => ['Boa escrita'],
        'pontos_atencao' => [],
        'sugestoes_acao' => ['Sugerir leitura'],
    ], $overrides));
}

beforeEach(function () {
    Event::fake();
});

it('persists a validated result and marks the analysis completed', function () {
    fakeDiaryProvider(diaryValidPayload());
    $analysis = makeDiaryAnalysis();

    runDiaryJob($analysis);

    $analysis->refresh();
    expect($analysis->status)->toBe(DiaryAnalysis::STATUS_COMPLETED);
    expect($analysis->failure_reason)->toBeNull();
    expect($analysis->result['indicadores']['compreensao'])->toBe(4);
    expect($analysis->result['schema_version'])->toBe(2);
});

it('extracts valid alerts into rows with evidence verified against the source', function () {
    $content = 'Não consegui acompanhar a aula, estava muito cansado e desmotivado.';
    fakeDiaryProvider(diaryValidPayload([
        'alertas' => [
            [
                'tipo' => 'desmotivacao',
                'severidade' => 'warning',
                'titulo' => 'Sinais de desânimo',
                'evidencia' => 'estava muito cansado',
            ],
            [
                'tipo' => 'sobrecarga',
                'severidade' => 'info',
                'titulo' => 'Evidência inventada',
                'evidencia' => 'frase que o aluno jamais escreveu',
            ],
        ],
    ]));
    $analysis = makeDiaryAnalysis($content);

    runDiaryJob($analysis);

    $alerts = $analysis->alerts()->get();
    expect($alerts)->toHaveCount(2);
    expect($alerts->firstWhere('type', 'desmotivacao')->evidence)->toBe('estava muito cansado');
    expect($alerts->firstWhere('type', 'sobrecarga')->evidence)->toBeNull();
    expect($alerts->every(fn ($a) => $a->status === DiaryAnalysisAlert::STATUS_PENDING))->toBeTrue();
});

it('marks the analysis failed with invalid_schema on malformed output', function () {
    fakeDiaryProvider(json_encode(['resumo' => 'curto']));
    $analysis = makeDiaryAnalysis();

    runDiaryJob($analysis);

    $analysis->refresh();
    expect($analysis->status)->toBe(DiaryAnalysis::STATUS_FAILED);
    expect($analysis->failure_reason)->toBe(DiaryAnalysis::FAILURE_INVALID_SCHEMA);
    expect($analysis->alerts()->count())->toBe(0);
});

it('marks the analysis failed with provider_empty on empty output', function () {
    fakeDiaryProvider(json_encode([]));
    $analysis = makeDiaryAnalysis();

    runDiaryJob($analysis);

    $analysis->refresh();
    expect($analysis->status)->toBe(DiaryAnalysis::STATUS_FAILED);
    expect($analysis->failure_reason)->toBe(DiaryAnalysis::FAILURE_PROVIDER_EMPTY);
});

it('marks failed and rethrows on a provider error', function () {
    fakeDiaryProvider(['error' => 'server'], 500);
    $analysis = makeDiaryAnalysis();

    expect(fn () => runDiaryJob($analysis))->toThrow(\App\Exceptions\AiProviderException::class);

    $analysis->refresh();
    expect($analysis->status)->toBe(DiaryAnalysis::STATUS_FAILED);
    expect($analysis->failure_reason)->toBe(DiaryAnalysis::FAILURE_PROVIDER_ERROR);
});

it('replaces previous alerts when reprocessed', function () {
    $content = 'estava muito cansado durante a aula de hoje.';

    $firstBody = ['choices' => [['message' => ['content' => diaryValidPayload([
        'alertas' => [
            ['tipo' => 'sobrecarga', 'severidade' => 'warning', 'titulo' => 'Primeira', 'evidencia' => 'muito cansado'],
        ],
    ])]]]];
    $secondBody = ['choices' => [['message' => ['content' => diaryValidPayload([
        'alertas' => [
            ['tipo' => 'desmotivacao', 'severidade' => 'info', 'titulo' => 'Segunda'],
            ['tipo' => 'ausencia_reflexao', 'severidade' => 'info', 'titulo' => 'Terceira'],
        ],
    ])]]]];

    Http::fakeSequence()
        ->push($firstBody, 200)
        ->push($secondBody, 200);

    $analysis = makeDiaryAnalysis($content);
    runDiaryJob($analysis);
    expect($analysis->alerts()->count())->toBe(1);

    runDiaryJob($analysis->fresh());

    $alerts = $analysis->alerts()->get();
    expect($alerts)->toHaveCount(2);
    expect($alerts->pluck('type')->all())->toEqualCanonicalizing(['desmotivacao', 'ausencia_reflexao']);
});

it('stores the raw response encrypted at rest', function () {
    fakeDiaryProvider(diaryValidPayload());
    $analysis = makeDiaryAnalysis();

    runDiaryJob($analysis);

    $rawColumn = \Illuminate\Support\Facades\DB::table('diary_analyses')
        ->where('id', $analysis->id)
        ->value('raw_response');

    expect($rawColumn)->not->toContain('compreensao');
    expect($analysis->fresh()->raw_response)->toContain('compreensao');
});
