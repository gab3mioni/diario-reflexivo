<?php

namespace App\Services\Chat;

use App\Contracts\Chat\BranchClassifierContract;
use App\Models\AiProviderConfig;
use App\Models\AnalysisPrompt;
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
     * @param  string  $question    Texto da pergunta apresentada ao aluno.
     * @param  string  $answer      Resposta do aluno.
     * @param  array<int, array{edge_id: string, description: string}>  $candidates  Arestas candidatas.
     * @return string  ID da aresta escolhida, ou '' se nenhuma correspondeu (fallback para padrão).
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
     * Decide se uma sub-conversa livre deve continuar ou encerrar.
     *
     * @param  string  $question  Texto da pergunta apresentada ao aluno.
     * @param  string  $answer    Resposta do aluno.
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
     * Chama o provedor de IA para classificar o payload fornecido.
     *
     * @param  array<string, mixed>  $payload  Dados de entrada para o classificador.
     * @return array<string, mixed>  Resposta decodificada do classificador.
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
