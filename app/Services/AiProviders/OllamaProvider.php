<?php

namespace App\Services\AiProviders;

use App\Exceptions\AiProviderException;
use Illuminate\Support\Facades\Http;

/**
 * Provedor de IA para instâncias locais do Ollama.
 */
class OllamaProvider extends AiProvider
{
    /** {@inheritDoc} */
    protected function buildRequestPayload(string $systemPrompt, string $userContent): array
    {
        return [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userContent],
            ],
            'stream' => false,
            'options' => [
                'temperature' => $this->temperature,
            ],
        ];
    }

    /** {@inheritDoc} */
    protected function sendRequest(array $payload): array
    {
        $baseUrl = $this->baseUrl ?: 'http://host.docker.internal:11434';

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->timeout(300)->post("{$baseUrl}/api/chat", $payload);

        if ($response->failed()) {
            throw AiProviderException::requestFailed(
                $this->providerName(),
                $response->body(),
            );
        }

        return $response->json();
    }

    /** {@inheritDoc} */
    protected function extractContent(array $responseData): string
    {
        return $responseData['message']['content'] ?? '';
    }

    /** {@inheritDoc} */
    protected function providerName(): string
    {
        return 'ollama';
    }

    /** {@inheritDoc} */
    public function ping(): void
    {
        $baseUrl = $this->baseUrl ?: 'http://host.docker.internal:11434';

        try {
            $response = Http::timeout(8)->get("{$baseUrl}/api/tags");
        } catch (\Throwable $e) {
            throw AiProviderException::requestFailed($this->providerName(), $e->getMessage());
        }

        if ($response->failed()) {
            throw AiProviderException::requestFailed(
                $this->providerName(),
                "HTTP {$response->status()}",
            );
        }
    }
}
