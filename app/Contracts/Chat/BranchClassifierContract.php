<?php

namespace App\Contracts\Chat;

use App\Services\Chat\BranchClassifierException;

/**
 * Contrato para o classificador de branches do chat.
 *
 * A implementação padrão ({@see \App\Services\Chat\BranchClassifier}) consome
 * o provedor de IA ativo. Em testes, injete um fake via container binding.
 */
interface BranchClassifierContract
{
    /**
     * Escolhe qual edge de saída seguir dada uma resposta livre do aluno.
     *
     * @param  array<int, array{edge_id: string, description: string}>  $candidates
     * @return string  edge_id escolhido, ou '' quando nenhum casou (caller usa default).
     *
     * @throws BranchClassifierException
     */
    public function classifyBranch(string $question, string $answer, array $candidates): string;

    /**
     * Decide se uma sub-conversa free-talk deve continuar ou sair.
     *
     * @return 'continue'|'exit'
     *
     * @throws BranchClassifierException
     */
    public function classifyContinuation(string $question, string $answer): string;
}
