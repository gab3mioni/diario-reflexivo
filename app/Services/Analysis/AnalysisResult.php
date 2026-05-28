<?php

namespace App\Services\Analysis;

/**
 * Resultado validado e normalizado de uma análise de diário.
 *
 * Value object imutável produzido por {@see AnalysisResultValidator}. O núcleo
 * (resumo, indicadores, listas) é o contrato estável; os alertas são extraídos
 * para linhas próprias e NÃO são persistidos no campo result.
 */
final class AnalysisResult
{
    /**
     * @param  array<string, int>  $indicadores  Mapa indicador => nota 1-5.
     * @param  list<string>  $pontosFortes
     * @param  list<string>  $pontosAtencao
     * @param  list<string>  $sugestoesAcao
     * @param  list<array<string, mixed>>  $alertas  Alertas validados, extraídos para linhas.
     * @param  array<string, mixed>  $extensions  Blocos de extensão conhecidos e validados (evidencias, confianca, ...).
     */
    public function __construct(
        public readonly string $resumo,
        public readonly array $indicadores,
        public readonly array $pontosFortes,
        public readonly array $pontosAtencao,
        public readonly array $sugestoesAcao,
        public readonly array $alertas,
        public readonly array $extensions,
        public readonly int $schemaVersion,
    ) {}

    /**
     * Forma persistida no campo result. Não inclui alertas (viram linhas próprias).
     *
     * @return array<string, mixed>
     */
    public function toStorageArray(): array
    {
        return array_merge([
            'resumo' => $this->resumo,
            'indicadores' => $this->indicadores,
            'pontos_fortes' => $this->pontosFortes,
            'pontos_atencao' => $this->pontosAtencao,
            'sugestoes_acao' => $this->sugestoesAcao,
            'schema_version' => $this->schemaVersion,
        ], $this->extensions);
    }
}
