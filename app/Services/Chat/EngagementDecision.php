<?php

namespace App\Services\Chat;

/**
 * DTO com o sinal de engajamento devolvido pelo classifier no modo "engagement".
 *
 * Decisões possíveis:
 * - "continue": aluno engajado, fluxo normal.
 * - "reengage": baixo engajamento pontual, vale uma tentativa de destravar.
 * - "ask_to_end": baixo engajamento persistente; sugerir encerramento.
 * - "exit": recusa explícita; sair sem perguntar.
 */
final readonly class EngagementDecision
{
    /** Decisão indicando que o aluno está engajado o suficiente para seguir. */
    public const DECISION_CONTINUE = 'continue';

    /** Decisão indicando tentativa de re-engajar antes de cogitar encerrar. */
    public const DECISION_REENGAGE = 'reengage';

    /** Decisão indicando que o condutor deve perguntar ao aluno se quer encerrar. */
    public const DECISION_ASK_TO_END = 'ask_to_end';

    /** Decisão indicando recusa explícita; encerrar sem perguntar. */
    public const DECISION_EXIT = 'exit';

    /** Nível de engajamento alto. */
    public const LEVEL_HIGH = 'high';

    /** Nível de engajamento médio. */
    public const LEVEL_MEDIUM = 'medium';

    /** Nível de engajamento baixo. */
    public const LEVEL_LOW = 'low';

    /**
     * @param  ?string  $engagementLevel  "high", "medium", "low" ou null se indeterminado.
     * @param  string  $decision  Uma das DECISION_* constants.
     * @param  ?string  $rationale  Frase curta em PT-BR explicando a decisão.
     * @param  ?int  $promptVersionId  ID da versão do prompt que tomou esta decisão.
     */
    public function __construct(
        public ?string $engagementLevel,
        public string $decision,
        public ?string $rationale,
        public ?int $promptVersionId,
    ) {}
}
