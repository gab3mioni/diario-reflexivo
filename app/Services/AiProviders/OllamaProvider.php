<?php

namespace App\Services\AiProviders;

use App\Exceptions\AiProviderException;
use Illuminate\Support\Facades\Http;

class OllamaProvider extends AiProvider
{
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

    protected function extractContent(array $responseData): string
    {
        return $responseData['message']['content'] ?? '';
    }

    protected function providerName(): string
    {
        return 'ollama';
    }
}
