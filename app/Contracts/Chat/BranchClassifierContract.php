<?php

namespace App\Contracts\Chat;

use App\Services\Chat\BranchClassifierException;
use App\Services\Chat\BranchDecision;
use App\Services\Chat\ContinuationDecision;
use App\Services\Chat\EngagementDecision;

/**
 * Contrato para o classificador de ramificação do chat reflexivo.
 *
 * Responsável por determinar qual caminho seguir no grafo de perguntas
 * com base na resposta do aluno.
 */
interface BranchClassifierContract
{
    /**
     * Classifica a resposta do aluno para determinar qual aresta seguir no grafo.
     *
     * @param  string  $question  Texto da pergunta apresentada ao aluno.
     * @param  string  $answer  Resposta do aluno.
     * @param  array<int, array{edge_id: string, description: string}>  $candidates  Arestas candidatas.
     * @return BranchDecision Aresta escolhida e versão do prompt usada na decisão.
     *
     * @throws BranchClassifierException
     */
    public function classifyBranch(string $question, string $answer, array $candidates): BranchDecision;

    /**
     * Classifica se o aluno deseja continuar a conversa livre ou encerrar.
     *
     * @param  string  $question  Texto da pergunta apresentada ao aluno.
     * @param  string  $answer  Resposta do aluno.
     * @return ContinuationDecision Decisão binária e versão do prompt usada.
     *
     * @throws BranchClassifierException
     */
    public function classifyContinuation(string $question, string $answer): ContinuationDecision;

    /**
     * Avalia o engajamento do aluno em um espaço de conversa livre.
     *
     * @param  string  $question  Texto da pergunta apresentada ao aluno.
     * @param  string  $answer  Resposta atual do aluno.
     * @param  array<int, string>  $recentTurns  Até 3 respostas anteriores do aluno no mesmo nó, da mais antiga para a mais recente.
     * @return EngagementDecision Nível, decisão sugerida e versão do prompt.
     *
     * @throws BranchClassifierException
     */
    public function classifyEngagement(string $question, string $answer, array $recentTurns = []): EngagementDecision;
}
