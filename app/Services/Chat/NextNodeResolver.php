<?php

namespace App\Services\Chat;

use App\Models\QuestionScript;

class NextNodeResolver
{
    public function __construct(private readonly BranchClassifier $classifier)
    {
    }

    /**
     * Decide which node to go to next from the current node, given the student's
     * answer. Returns a result describing the chosen node and how the decision
     * was reached (for telemetry on the bot message).
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
