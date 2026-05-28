<?php

namespace App\Services\Chat;

/**
 * DTO com o resultado da classificação de continuação do classifier.
 *
 * Carrega a decisão binária (continue/exit) e a versão do prompt que a tomou,
 * para registro em ChatMessage.prompt_version_id.
 */
final readonly class ContinuationDecision
{
    /**
     * @param  'continue'|'exit'  $decision  Decisão do classifier.
     * @param  ?int  $promptVersionId  ID da versão do prompt usada na decisão.
     */
    public function __construct(
        public string $decision,
        public ?int $promptVersionId,
    ) {}
}
