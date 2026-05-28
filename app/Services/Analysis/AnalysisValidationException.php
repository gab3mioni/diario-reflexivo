<?php

namespace App\Services\Analysis;

use App\Models\DiaryAnalysis;
use RuntimeException;

/**
 * Lançada quando a saída da IA não satisfaz o contrato de schema ou as
 * verificações de plausibilidade. Carrega uma razão de falha estruturada
 * (uma das constantes FAILURE_* de {@see DiaryAnalysis}) para persistência.
 */
class AnalysisValidationException extends RuntimeException
{
    public function __construct(
        public readonly string $failureReason,
        string $message,
    ) {
        parent::__construct($message);
    }

    /**
     * Saída não satisfaz o contrato de schema-core (campos/typos obrigatórios).
     */
    public static function invalidSchema(string $detail): self
    {
        return new self(DiaryAnalysis::FAILURE_INVALID_SCHEMA, $detail);
    }

    /**
     * Saída é estruturalmente válida mas implausível (provável alucinação).
     */
    public static function implausible(string $detail): self
    {
        return new self(DiaryAnalysis::FAILURE_IMPLAUSIBLE, $detail);
    }
}
