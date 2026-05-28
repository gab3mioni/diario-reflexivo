<?php

namespace App\Services\Chat;

use App\Contracts\Chat\BranchClassifierContract;
use App\Models\AiProviderConfig;
use App\Models\AnalysisPrompt;
use App\Models\AnalysisPromptVersion;
use App\Services\AiProviders\AiProvider;
use RuntimeException;
use Throwable;

/**
 * Implementação do classificador de ramificação do chat que utiliza IA
 * para decidir qual caminho seguir no grafo de perguntas.
 */
class BranchClassifier implements BranchClassifierContract
{
    /**
     * Escolhe qual aresta seguir com base na resposta do aluno.
     *
     * Apenas arestas não-padrão são oferecidas ao classificador — a padrão é
     * reservada como fallback determinístico.
     *
     * @param  string  $question  Texto da pergunta apresentada ao aluno.
     * @param  string  $answer  Resposta do aluno.
     * @param  array<int, array{edge_id: string, description: string}>  $candidates  Arestas candidatas.
     * @return BranchDecision Edge escolhida (ou '' para fallback) e versão do prompt.
     *
     * @throws BranchClassifierException
     */
    public function classifyBranch(string $question, string $answer, array $candidates): BranchDecision
    {
        $payload = [
            'mode' => 'branch',
            'question' => $question,
            'answer' => $answer,
            'edges' => array_values($candidates),
        ];

        [$decoded, $version] = $this->callClassifier($payload);

        return new BranchDecision(
            edgeId: (string) ($decoded['edge_id'] ?? ''),
            promptVersionId: $version->id,
        );
    }

    /**
     * Decide se uma sub-conversa livre deve continuar ou encerrar.
     *
     * @param  string  $question  Texto da pergunta apresentada ao aluno.
     * @param  string  $answer  Resposta do aluno.
     *
     * @throws BranchClassifierException
     */
    public function classifyContinuation(string $question, string $answer): ContinuationDecision
    {
        $payload = [
            'mode' => 'continuation',
            'question' => $question,
            'answer' => $answer,
        ];

        [$decoded, $version] = $this->callClassifier($payload);
        $decision = (string) ($decoded['decision'] ?? 'exit');

        return new ContinuationDecision(
            decision: $decision === 'continue' ? 'continue' : 'exit',
            promptVersionId: $version->id,
        );
    }

    /**
     * Avalia o engajamento do aluno em uma conversa livre.
     *
     * @param  string  $question  Texto da pergunta apresentada ao aluno.
     * @param  string  $answer  Resposta atual do aluno.
     * @param  array<int, string>  $recentTurns  Até 3 respostas anteriores do aluno no mesmo nó.
     * @return EngagementDecision Nível, decisão e versão do prompt usada.
     *
     * @throws BranchClassifierException
     */
    public function classifyEngagement(string $question, string $answer, array $recentTurns = []): EngagementDecision
    {
        $payload = [
            'mode' => 'engagement',
            'question' => $question,
            'answer' => $answer,
            'recent_turns' => array_values(array_filter(
                $recentTurns,
                static fn ($t) => is_string($t) && $t !== '',
            )),
        ];

        [$decoded, $version] = $this->callClassifier($payload);

        return new EngagementDecision(
            engagementLevel: $this->normalizeLevel($decoded['engagement_level'] ?? null),
            decision: $this->normalizeDecision($decoded['decision'] ?? null),
            rationale: isset($decoded['rationale']) && is_string($decoded['rationale'])
                ? mb_substr($decoded['rationale'], 0, 480)
                : null,
            promptVersionId: $version->id,
        );
    }

    /**
     * Normaliza o engagement_level vindo do modelo. Devolve null se inválido.
     */
    private function normalizeLevel(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        return match ($value) {
            EngagementDecision::LEVEL_HIGH,
            EngagementDecision::LEVEL_MEDIUM,
            EngagementDecision::LEVEL_LOW => $value,
            default => null,
        };
    }

    /**
     * Normaliza a decision vinda do modelo. Quando inválida, devolve "continue" — o
     * comportamento conservador combinado com a regra de falha do condutor (Fase 3).
     */
    private function normalizeDecision(mixed $value): string
    {
        if (! is_string($value)) {
            return EngagementDecision::DECISION_CONTINUE;
        }

        return match ($value) {
            EngagementDecision::DECISION_CONTINUE,
            EngagementDecision::DECISION_REENGAGE,
            EngagementDecision::DECISION_ASK_TO_END,
            EngagementDecision::DECISION_EXIT => $value,
            default => EngagementDecision::DECISION_CONTINUE,
        };
    }

    /**
     * Chama o provedor de IA para classificar o payload fornecido.
     *
     * Resolve a versão ativa do prompt (pin do admin → fallback latestVersion)
     * e devolve a resposta decodificada junto da versão usada, para que o caller
     * possa persistir prompt_version_id na ChatMessage gerada.
     *
     * @param  array<string, mixed>  $payload  Dados de entrada para o classificador.
     * @return array{0: array<string, mixed>, 1: AnalysisPromptVersion} Tupla [resposta decodificada, versão usada].
     *
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

            $version = $prompt->resolveActiveVersion();
            if (! $version) {
                throw new RuntimeException('Branch classifier prompt has no versions.');
            }

            $provider = AiProvider::fromConfig($config);

            $decoded = $provider->analyze($version->content, json_encode($payload, JSON_UNESCAPED_UNICODE));

            return [$decoded, $version];
        } catch (Throwable $e) {
            throw new BranchClassifierException($e->getMessage(), 0, $e);
        }
    }
}
