<?php

namespace App\Services\Analysis;

use App\Models\DiaryAnalysisAlert;
use Normalizer;

/**
 * Valida e normaliza a saída bruta da IA antes da persistência.
 *
 * Serviço puro (sem I/O): recebe o array decodificado e o texto-fonte do aluno,
 * devolve um {@see AnalysisResult} ou lança {@see AnalysisValidationException}.
 *
 * Defesas aplicadas:
 * - schema-core obrigatório com tipos e clamps (indicadores 1-5);
 * - listas saneadas (itens vazios removidos, comprimento e contagem limitados);
 * - alertas filtrados por allow-list de tipo/severidade, com teto por resposta;
 * - evidência aceita apenas se for substring do texto-fonte (anti-alucinação);
 * - plausibilidade (uniformidade degenerada, piso de comprimento do resumo).
 */
class AnalysisResultValidator
{
    /** Indicadores reconhecidos do schema-core, todos obrigatórios. */
    public const INDICATORS = [
        'compreensao',
        'engajamento',
        'pensamento_critico',
        'clareza_expressao',
        'reflexao_pessoal',
    ];

    /** Tipos de alerta aceitos. Alertas de tipo desconhecido são descartados. */
    public const ALERT_TYPES = [
        'desmotivacao',
        'sobrecarga',
        'dificuldade_conceitual',
        'ausencia_reflexao',
        'sinal_socioemocional',
        'inconsistencia',
        'autoria_suspeita',
    ];

    private const RESUMO_MIN = 12;

    private const RESUMO_MAX = 2000;

    private const LIST_ITEM_MAX = 500;

    private const LIST_COUNT_MAX = 10;

    private const TITLE_MAX = 160;

    private const DETAIL_MAX = 500;

    private const EVIDENCE_MAX = 500;

    private const ALERTS_PER_RESPONSE_MAX = 5;

    private const ZERO_WIDTH = "/[\x{200B}-\x{200D}\x{2060}\x{FEFF}]/u";

    public function __construct(
        private readonly ExtensionBlockRegistry $registry = new ExtensionBlockRegistry,
    ) {}

    /**
     * Valida o resultado bruto contra o texto-fonte do aluno.
     *
     * @param  array<string, mixed>  $raw  Resultado decodificado da IA.
     * @param  string  $sourceText  Conteúdo da resposta do aluno (para checagem de evidência).
     *
     * @throws AnalysisValidationException
     */
    public function validate(array $raw, string $sourceText): AnalysisResult
    {
        $resumo = $this->validateResumo($raw['resumo'] ?? null);
        $indicadores = $this->validateIndicadores($raw['indicadores'] ?? null);

        $this->assertPlausible($resumo, $indicadores);

        $canonicalSource = $this->canonicalize($sourceText);

        return new AnalysisResult(
            resumo: $resumo,
            indicadores: $indicadores,
            pontosFortes: $this->sanitizeList($raw['pontos_fortes'] ?? []),
            pontosAtencao: $this->sanitizeList($raw['pontos_atencao'] ?? []),
            sugestoesAcao: $this->sanitizeList($raw['sugestoes_acao'] ?? []),
            alertas: $this->validateAlertas($raw['alertas'] ?? [], $canonicalSource),
            extensions: $this->validateExtensions($raw),
            schemaVersion: 2,
        );
    }

    /**
     * @throws AnalysisValidationException
     */
    private function validateResumo(mixed $value): string
    {
        if (! is_string($value)) {
            throw AnalysisValidationException::invalidSchema('resumo ausente ou não textual.');
        }

        $resumo = trim($this->stripControl($value));

        if (mb_strlen($resumo) < self::RESUMO_MIN) {
            throw AnalysisValidationException::invalidSchema('resumo abaixo do comprimento mínimo.');
        }

        return mb_substr($resumo, 0, self::RESUMO_MAX);
    }

    /**
     * @return array<string, int>
     *
     * @throws AnalysisValidationException
     */
    private function validateIndicadores(mixed $value): array
    {
        if (! is_array($value)) {
            throw AnalysisValidationException::invalidSchema('indicadores ausentes ou inválidos.');
        }

        $result = [];

        foreach (self::INDICATORS as $key) {
            if (! array_key_exists($key, $value) || ! is_numeric($value[$key])) {
                throw AnalysisValidationException::invalidSchema("indicador {$key} ausente ou não numérico.");
            }

            $result[$key] = $this->clamp((int) round((float) $value[$key]), 1, 5);
        }

        return $result;
    }

    /**
     * Verifica sinais grosseiros de alucinação/preenchimento degenerado.
     *
     * @param  array<string, int>  $indicadores
     *
     * @throws AnalysisValidationException
     */
    private function assertPlausible(string $resumo, array $indicadores): void
    {
        $values = array_values($indicadores);
        $allEqual = count(array_unique($values)) === 1;

        if ($allEqual && mb_strlen($resumo) < 20) {
            throw AnalysisValidationException::implausible('indicadores uniformes com resumo trivial.');
        }
    }

    /**
     * @return list<string>
     */
    private function sanitizeList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            if (! is_string($item)) {
                continue;
            }

            $clean = trim($this->stripControl($item));

            if ($clean === '') {
                continue;
            }

            $items[] = mb_substr($clean, 0, self::LIST_ITEM_MAX);

            if (count($items) >= self::LIST_COUNT_MAX) {
                break;
            }
        }

        return $items;
    }

    /**
     * @param  array<int, mixed>|mixed  $value
     * @return list<array<string, mixed>>
     */
    private function validateAlertas(mixed $value, string $canonicalSource): array
    {
        if (! is_array($value)) {
            return [];
        }

        $alertas = [];

        foreach ($value as $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $alert = $this->validateSingleAlert($raw, $canonicalSource);

            if ($alert !== null) {
                $alertas[] = $alert;
            }

            if (count($alertas) >= self::ALERTS_PER_RESPONSE_MAX) {
                break;
            }
        }

        return $alertas;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return ?array<string, mixed>
     */
    private function validateSingleAlert(array $raw, string $canonicalSource): ?array
    {
        $type = is_string($raw['tipo'] ?? null) ? $raw['tipo'] : null;
        $severity = is_string($raw['severidade'] ?? null) ? $raw['severidade'] : null;
        $title = is_string($raw['titulo'] ?? null) ? trim($this->stripControl($raw['titulo'])) : '';

        if (! in_array($type, self::ALERT_TYPES, true)) {
            return null;
        }

        if (! in_array($severity, DiaryAnalysisAlert::SEVERITIES, true)) {
            return null;
        }

        if ($title === '') {
            return null;
        }

        return [
            'type' => $type,
            'severity' => $severity,
            'title' => mb_substr($title, 0, self::TITLE_MAX),
            'detail' => $this->optionalText($raw['detalhe'] ?? null, self::DETAIL_MAX),
            'evidence' => $this->validateEvidence($raw['evidencia'] ?? null, $canonicalSource),
            'confidence' => $this->optionalConfidence($raw['confianca'] ?? null),
        ];
    }

    /**
     * Aceita a evidência apenas se, normalizada, for substring do texto-fonte.
     * Armazena o trecho original (aparado e limitado), não o normalizado.
     */
    private function validateEvidence(mixed $value, string $canonicalSource): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $original = trim($this->stripControl($value));

        if ($original === '') {
            return null;
        }

        $canonicalEvidence = $this->canonicalize($original);

        if ($canonicalEvidence === '' || ! str_contains($canonicalSource, $canonicalEvidence)) {
            return null;
        }

        return mb_substr($original, 0, self::EVIDENCE_MAX);
    }

    private function optionalText(mixed $value, int $max): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $clean = trim($this->stripControl($value));

        return $clean === '' ? null : mb_substr($clean, 0, $max);
    }

    private function optionalConfidence(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        return $this->clamp((int) round((float) $value), 0, 100);
    }

    /**
     * Mantém apenas blocos de extensão conhecidos (exceto alertas, que viram linhas)
     * e os saneia. Blocos desconhecidos são descartados.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function validateExtensions(array $raw): array
    {
        $extensions = [];

        if (isset($raw[ExtensionBlockRegistry::BLOCK_EVIDENCIAS]) && is_array($raw[ExtensionBlockRegistry::BLOCK_EVIDENCIAS])) {
            $extensions[ExtensionBlockRegistry::BLOCK_EVIDENCIAS] = $this->sanitizeStringMap($raw[ExtensionBlockRegistry::BLOCK_EVIDENCIAS]);
        }

        if (isset($raw[ExtensionBlockRegistry::BLOCK_CONFIANCA]) && is_array($raw[ExtensionBlockRegistry::BLOCK_CONFIANCA])) {
            $extensions[ExtensionBlockRegistry::BLOCK_CONFIANCA] = $this->sanitizeConfidenceMap($raw[ExtensionBlockRegistry::BLOCK_CONFIANCA]);
        }

        return array_filter($extensions, fn ($block) => $block !== []);
    }

    /**
     * @param  array<string, mixed>  $map
     * @return array<string, string>
     */
    private function sanitizeStringMap(array $map): array
    {
        $clean = [];

        foreach ($map as $key => $val) {
            if (! is_string($key) || ! in_array($key, self::INDICATORS, true) || ! is_string($val)) {
                continue;
            }

            $text = trim($this->stripControl($val));

            if ($text !== '') {
                $clean[$key] = mb_substr($text, 0, self::EVIDENCE_MAX);
            }
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $map
     * @return array<string, int>
     */
    private function sanitizeConfidenceMap(array $map): array
    {
        $clean = [];

        foreach ($map as $key => $val) {
            if (! is_string($key) || ! in_array($key, self::INDICATORS, true) || ! is_numeric($val)) {
                continue;
            }

            $clean[$key] = $this->clamp((int) round((float) $val), 0, 100);
        }

        return $clean;
    }

    /**
     * Normaliza para comparação: remove zero-width, aplica NFC, casefold,
     * colapsa espaços. Usada apenas na checagem de substring de evidência.
     */
    private function canonicalize(string $text): string
    {
        $text = $this->stripControl($text);

        if (class_exists(Normalizer::class)) {
            $normalized = Normalizer::normalize($text, Normalizer::FORM_C);

            if ($normalized !== false) {
                $text = $normalized;
            }
        }

        $text = mb_strtolower($text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * Remove caracteres zero-width que poderiam burlar a checagem de substring.
     */
    private function stripControl(string $text): string
    {
        return preg_replace(self::ZERO_WIDTH, '', $text) ?? $text;
    }

    private function clamp(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
    }
}
