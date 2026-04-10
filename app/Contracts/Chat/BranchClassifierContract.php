<?php

namespace App\Contracts\Chat;

use App\Services\Chat\BranchClassifierException;

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
     * @param  string  $question    Texto da pergunta apresentada ao aluno.
     * @param  string  $answer      Resposta do aluno.
     * @param  array<int, array{edge_id: string, description: string}>  $candidates  Arestas candidatas.
     * @return string  ID da aresta selecionada.
     *
     * @throws BranchClassifierException
     */
    public function classifyBranch(string $question, string $answer, array $candidates): string;

    /**
     * Classifica se o aluno deseja continuar a conversa livre ou encerrar.
     *
     * @param  string  $question  Texto da pergunta apresentada ao aluno.
     * @param  string  $answer    Resposta do aluno.
     * @return 'continue'|'exit'
     *
     * @throws BranchClassifierException
     */
    public function classifyContinuation(string $question, string $answer): string;
}
