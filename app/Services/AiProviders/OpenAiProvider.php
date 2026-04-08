<?php

namespace App\Services\AiProviders;

use App\Exceptions\AiProviderException;
use Illuminate\Support\Facades\Http;

class OpenAiProvider extends AiProvider
{
    protected function buildRequestPayload(string $systemPrompt, string $userContent): array
    {
        return [
            'model' => $this->model,
            'temperature' => $this->temperature,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userContent],
            ],
        ];
    }

    protected function sendRequest(array $payload): array
    {
        $baseUrl = $this->baseUrl ?: 'https://api.openai.com';

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ])->timeout(120)->post("{$baseUrl}/v1/chat/completions", $payload);

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
        return $responseData['choices'][0]['message']['content'] ?? '';
    }

    protected function providerName(): string
    {
        return 'openai';
    }

    public function ping(): void
    {
        $baseUrl = $this->baseUrl ?: 'https://api.openai.com';

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
            ])->timeout(8)->get("{$baseUrl}/v1/models");
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
