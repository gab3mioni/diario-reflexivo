<?php

namespace App\Services\Chat;

/**
 * DTO com o resultado da resolução do próximo nó no grafo de perguntas.
 */
final class ResolveResult
{
    /**
     * @param  ?string  $nextNodeId        ID do próximo nó, ou null se o fluxo terminou.
     * @param  string   $classifierStatus  Status da classificação ('ok', 'skipped', 'failed', 'default_fallback').
     * @param  ?string  $classifierReason  Motivo quando a classificação não foi 'ok'.
     */
    public function __construct(
        public readonly ?string $nextNodeId,
        public readonly string $classifierStatus,
        public readonly ?string $classifierReason,
    ) {
    }
}
