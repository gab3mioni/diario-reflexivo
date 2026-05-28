<?php

use App\Models\DiaryAnalysisAlert;
use App\Services\Analysis\AnalysisResultValidator;
use App\Services\Analysis\AnalysisValidationException;

beforeEach(function () {
    $this->validator = new AnalysisResultValidator;
});

function diaryValidCore(array $overrides = []): array
{
    return array_merge([
        'resumo' => 'O aluno demonstrou boa compreensão e conectou com experiências próprias.',
        'indicadores' => [
            'compreensao' => 4,
            'engajamento' => 3,
            'pensamento_critico' => 4,
            'clareza_expressao' => 5,
            'reflexao_pessoal' => 3,
        ],
        'pontos_fortes' => ['Boa articulação das ideias'],
        'pontos_atencao' => [],
        'sugestoes_acao' => ['Propor leitura complementar'],
    ], $overrides);
}

test('validates a well-formed core result', function () {
    $result = $this->validator->validate(diaryValidCore(), 'texto do aluno');

    expect($result->resumo)->toContain('compreensão');
    expect($result->indicadores)->toHaveKeys(AnalysisResultValidator::INDICATORS);
    expect($result->pontosFortes)->toBe(['Boa articulação das ideias']);
    expect($result->schemaVersion)->toBe(2);
});

test('clamps indicators outside the 1-5 range', function () {
    $result = $this->validator->validate(diaryValidCore([
        'indicadores' => [
            'compreensao' => 9,
            'engajamento' => 0,
            'pensamento_critico' => 3,
            'clareza_expressao' => -2,
            'reflexao_pessoal' => 5,
        ],
    ]), 'texto');

    expect($result->indicadores['compreensao'])->toBe(5);
    expect($result->indicadores['engajamento'])->toBe(1);
    expect($result->indicadores['clareza_expressao'])->toBe(1);
});

test('rejects a missing indicator as invalid schema', function () {
    $payload = diaryValidCore();
    unset($payload['indicadores']['engajamento']);

    expect(fn () => $this->validator->validate($payload, 'texto'))
        ->toThrow(AnalysisValidationException::class);
});

test('rejects a too-short resumo as invalid schema', function () {
    try {
        $this->validator->validate(diaryValidCore(['resumo' => 'curto']), 'texto');
        $this->fail('esperava exceção');
    } catch (AnalysisValidationException $e) {
        expect($e->failureReason)->toBe(\App\Models\DiaryAnalysis::FAILURE_INVALID_SCHEMA);
    }
});

test('rejects uniform indicators with a trivial resumo as implausible', function () {
    try {
        $this->validator->validate([
            'resumo' => 'ok aluno bom',
            'indicadores' => array_fill_keys(AnalysisResultValidator::INDICATORS, 3),
        ], 'texto');
        $this->fail('esperava exceção');
    } catch (AnalysisValidationException $e) {
        expect($e->failureReason)->toBe(\App\Models\DiaryAnalysis::FAILURE_IMPLAUSIBLE);
    }
});

test('drops empty and non-string list items and caps length', function () {
    $result = $this->validator->validate(diaryValidCore([
        'pontos_fortes' => ['  ', 'válido', 42, str_repeat('x', 999)],
    ]), 'texto');

    expect($result->pontosFortes)->toHaveCount(2);
    expect($result->pontosFortes[0])->toBe('válido');
    expect(mb_strlen($result->pontosFortes[1]))->toBe(500);
});

test('keeps valid alerts and drops unknown types', function () {
    $result = $this->validator->validate(diaryValidCore([
        'alertas' => [
            ['tipo' => 'desmotivacao', 'severidade' => 'warning', 'titulo' => 'Sinais de desânimo'],
            ['tipo' => 'tipo_inexistente', 'severidade' => 'critical', 'titulo' => 'Ignorar'],
            ['tipo' => 'sobrecarga', 'severidade' => 'invalida', 'titulo' => 'Sem severidade válida'],
        ],
    ]), 'texto');

    expect($result->alertas)->toHaveCount(1);
    expect($result->alertas[0]['type'])->toBe('desmotivacao');
});

test('caps alerts at five per response', function () {
    $alertas = array_fill(0, 8, [
        'tipo' => 'desmotivacao',
        'severidade' => DiaryAnalysisAlert::SEVERITY_INFO,
        'titulo' => 'Repetido',
    ]);

    $result = $this->validator->validate(diaryValidCore(['alertas' => $alertas]), 'texto');

    expect($result->alertas)->toHaveCount(5);
});

test('accepts alert evidence only when it is a substring of the source', function () {
    $source = 'Não consegui acompanhar a aula de hoje, estava muito cansado.';

    $result = $this->validator->validate(diaryValidCore([
        'alertas' => [
            ['tipo' => 'sobrecarga', 'severidade' => 'warning', 'titulo' => 'Cansaço', 'evidencia' => 'estava muito cansado'],
            ['tipo' => 'desmotivacao', 'severidade' => 'warning', 'titulo' => 'Inventado', 'evidencia' => 'frase que o aluno nunca escreveu'],
        ],
    ]), $source);

    expect($result->alertas[0]['evidence'])->toBe('estava muito cansado');
    expect($result->alertas[1]['evidence'])->toBeNull();
});

test('matches evidence despite case and whitespace differences', function () {
    $source = "Estava   MUITO    cansado\nontem.";

    $result = $this->validator->validate(diaryValidCore([
        'alertas' => [
            ['tipo' => 'sobrecarga', 'severidade' => 'warning', 'titulo' => 'x', 'evidencia' => 'muito cansado'],
        ],
    ]), $source);

    expect($result->alertas[0]['evidence'])->toBe('muito cansado');
});

test('clamps alert confidence to 0-100', function () {
    $result = $this->validator->validate(diaryValidCore([
        'alertas' => [
            ['tipo' => 'desmotivacao', 'severidade' => 'info', 'titulo' => 'x', 'confianca' => 250],
        ],
    ]), 'texto');

    expect($result->alertas[0]['confidence'])->toBe(100);
});

test('keeps known extension blocks and drops unknown ones', function () {
    $result = $this->validator->validate(diaryValidCore([
        'confianca' => ['compreensao' => 80, 'inexistente' => 50],
        'bloco_desconhecido' => ['qualquer' => 'coisa'],
    ]), 'texto');

    expect($result->extensions)->toHaveKey('confianca');
    expect($result->extensions['confianca'])->toBe(['compreensao' => 80]);
    expect($result->extensions)->not->toHaveKey('bloco_desconhecido');
});

test('strips zero-width characters before comparing evidence', function () {
    $source = 'palavra secreta no texto';
    $evidenceWithZeroWidth = "palavra\u{200B} secreta";

    $result = $this->validator->validate(diaryValidCore([
        'alertas' => [
            ['tipo' => 'desmotivacao', 'severidade' => 'info', 'titulo' => 'x', 'evidencia' => $evidenceWithZeroWidth],
        ],
    ]), $source);

    expect($result->alertas[0]['evidence'])->not->toBeNull();
});
