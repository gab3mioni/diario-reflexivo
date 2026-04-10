<?php

namespace App\Services\Chat;

use App\Contracts\Chat\BranchClassifierContract;
use App\Models\AiProviderConfig;
use App\Models\AnalysisPrompt;
use App\Services\AiProviders\AiProvider;
use RuntimeException;
use Throwable;

class BranchClassifier implements BranchClassifierContract
{
    /**
     * Choose which outgoing edge to follow given a free-text student answer.
     * Only non-default edges are offered to the classifier — the default is
     * reserved as a deterministic fallback and should not be "chosen" by the IA.
     *
     * @param  array<int, array{edge_id: string, description: string}>  $candidates
     * @return string  The chosen edge_id, or '' to indicate "none matched" (caller should fall back to default).
     *
     * @throws BranchClassifierException
     */
    public function classifyBranch(string $question, string $answer, array $candidates): string
    {
        $payload = [
            'mode' => 'branch',
            'question' => $question,
            'answer' => $answer,
            'edges' => array_values($candidates),
        ];

        $decoded = $this->callClassifier($payload);

        return (string) ($decoded['edge_id'] ?? '');
    }

    /**
     * Decide whether a free-talk sub-conversation should continue or exit.
     *
     * @return 'continue'|'exit'
     *
     * @throws BranchClassifierException
     */
    public function classifyContinuation(string $question, string $answer): string
    {
        $payload = [
            'mode' => 'continuation',
            'question' => $question,
            'answer' => $answer,
        ];

        $decoded = $this->callClassifier($payload);
        $decision = (string) ($decoded['decision'] ?? 'exit');

        return $decision === 'continue' ? 'continue' : 'exit';
    }

    /**
     * @throws BranchClassifierException
     */
    private function callClassifier(array $payload): array
    {
        try {
            $config = AiProviderConfig::active();
            if (! $config) {
                throw new RuntimeException('No active AI provider configured.');
            }

            $prompt = AnalysisPrompt::where('slug', 'branch-classifier')->first();
            if (! $prompt) {
                throw new RuntimeException('Branch classifier prompt not seeded.');
            }

            $version = $prompt->latestVersion;
            if (! $version) {
                throw new RuntimeException('Branch classifier prompt has no versions.');
            }

            $provider = AiProvider::fromConfig($config);

            return $provider->analyze($version->content, json_encode($payload, JSON_UNESCAPED_UNICODE));
        } catch (Throwable $e) {
            throw new BranchClassifierException($e->getMessage(), 0, $e);
        }
    }
}
