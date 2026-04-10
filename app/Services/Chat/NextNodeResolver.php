<?php

namespace App\Services\Chat;

use App\Contracts\Chat\BranchClassifierContract;
use App\Models\QuestionScript;

/**
 * Resolve o próximo nó no grafo de perguntas a partir da resposta do aluno.
 */
class NextNodeResolver
{
    /**
     * @param  BranchClassifierContract  $classifier  Classificador de ramificação por IA.
     */
    public function __construct(private readonly BranchClassifierContract $classifier)
    {
    }

    /**
     * Decide qual nó seguir a partir do nó atual com base na resposta do aluno.
     *
     * @param  QuestionScript  $script         Roteiro de perguntas.
     * @param  string          $currentNodeId  ID do nó atual.
     * @param  string          $studentAnswer  Texto da resposta do aluno.
     * @return ResolveResult  Resultado com o próximo nó e metadados da classificação.
     */
    public function resolve(
        QuestionScript $script,
        string $currentNodeId,
        string $studentAnswer,
    ): ResolveResult {
        $currentNode = $script->getNode($currentNodeId);
        if (! $currentNode) {
            return new ResolveResult(null, 'skipped', 'current node not found');
        }

        $outgoing = $script->getOutgoingEdges($currentNodeId);

        if (count($outgoing) === 0) {
            return new ResolveResult(null, 'skipped', 'no outgoing edges');
        }

        if (count($outgoing) === 1) {
            return new ResolveResult($outgoing[0]['target'] ?? null, 'skipped', null);
        }

        $collectionType = $currentNode['data']['collection_type'] ?? 'free_text';

        if ($collectionType === 'option') {
            return $this->resolveOption($studentAnswer, $outgoing, $script, $currentNodeId);
        }

        return $this->resolveFreeText(
            $currentNode['data']['message'] ?? '',
            $studentAnswer,
            $outgoing,
            $script,
            $currentNodeId,
        );
    }

    /**
     * Resolve o próximo nó quando o tipo de coleta é "option" (correspondência exata de label).
     *
     * @param  string                              $studentAnswer  Resposta do aluno.
     * @param  array<int, array<string, mixed>>    $outgoing       Arestas de saída.
     * @param  QuestionScript                      $script         Roteiro de perguntas.
     * @param  string                              $currentNodeId  ID do nó atual.
     * @return ResolveResult
     */
    private function resolveOption(
        string $studentAnswer,
        array $outgoing,
        QuestionScript $script,
        string $currentNodeId,
    ): ResolveResult {
        $needle = mb_strtolower(trim($studentAnswer));

        foreach ($outgoing as $edge) {
            $label = mb_strtolower(trim($edge['condition']['description'] ?? ''));
            if ($label !== '' && $label === $needle) {
                return new ResolveResult($edge['target'] ?? null, 'ok', null);
            }
        }

        $default = $script->getDefaultOutgoingEdge($currentNodeId);

        return new ResolveResult(
            $default['target'] ?? null,
            'default_fallback',
            'option label did not match any edge condition',
        );
    }

    /**
     * Resolve o próximo nó quando o tipo de coleta é "free_text" (classificação por IA).
     *
     * @param  string                              $question       Texto da pergunta.
     * @param  string                              $studentAnswer  Resposta do aluno.
     * @param  array<int, array<string, mixed>>    $outgoing       Arestas de saída.
     * @param  QuestionScript                      $script         Roteiro de perguntas.
     * @param  string                              $currentNodeId  ID do nó atual.
     * @return ResolveResult
     */
    private function resolveFreeText(
        string $question,
        string $studentAnswer,
        array $outgoing,
        QuestionScript $script,
        string $currentNodeId,
    ): ResolveResult {
        $candidates = [];
        foreach ($outgoing as $edge) {
            if (! empty($edge['is_default'])) {
                continue;
            }
            $candidates[] = [
                'edge_id' => (string) ($edge['id'] ?? ''),
                'description' => (string) ($edge['condition']['description'] ?? ''),
            ];
        }

        if (count($candidates) === 0) {
            $default = $script->getDefaultOutgoingEdge($currentNodeId);

            return new ResolveResult($default['target'] ?? null, 'skipped', null);
        }

        try {
            $chosenEdgeId = $this->classifier->classifyBranch($question, $studentAnswer, $candidates);
        } catch (BranchClassifierException $e) {
            $default = $script->getDefaultOutgoingEdge($currentNodeId);

            return new ResolveResult(
                $default['target'] ?? null,
                'failed',
                mb_substr($e->getMessage(), 0, 480),
            );
        }

        if ($chosenEdgeId === '') {
            $default = $script->getDefaultOutgoingEdge($currentNodeId);

            return new ResolveResult(
                $default['target'] ?? null,
                'default_fallback',
                'classifier returned empty choice',
            );
        }

        foreach ($outgoing as $edge) {
            if ((string) ($edge['id'] ?? '') === $chosenEdgeId) {
                return new ResolveResult($edge['target'] ?? null, 'ok', null);
            }
        }

        $default = $script->getDefaultOutgoingEdge($currentNodeId);

        return new ResolveResult(
            $default['target'] ?? null,
            'default_fallback',
            'classifier returned unknown edge_id',
        );
    }
}
