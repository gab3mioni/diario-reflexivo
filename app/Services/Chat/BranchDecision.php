<?php

namespace App\Services\Chat;

/**
 * DTO com o resultado da classificação de ramificação do classifier.
 *
 * Carrega o edge escolhido e a versão do prompt que tomou a decisão, para
 * registro em ChatMessage.prompt_version_id.
 */
final readonly class BranchDecision
{
    /**
     * @param  string  $edgeId  ID da aresta escolhida. Vazio significa fallback para a aresta padrão.
     * @param  ?int  $promptVersionId  ID da versão do prompt usada na decisão.
     */
    public function __construct(
        public string $edgeId,
        public ?int $promptVersionId,
    ) {}
}
