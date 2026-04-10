<?php

namespace App\Services\AiProviders;

use App\Exceptions\AiProviderException;
use Illuminate\Support\Facades\Http;

/**
 * Provedor de IA para a API Google Gemini.
 */
class GeminiProvider extends AiProvider
{
    /** {@inheritDoc} */
    protected function buildRequestPayload(string $systemPrompt, string $userContent): array
    {
        return [
            'system_instruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => $userContent]],
                ],
            ],
            'generationConfig' => [
                'temperature' => $this->temperature,
            ],
        ];
    }

    /** {@inheritDoc} */
    protected function sendRequest(array $payload): array
    {
        $baseUrl = $this->baseUrl ?: 'https://generativelanguage.googleapis.com';
        $url = "{$baseUrl}/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->timeout(120)->post($url, $payload);

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
        return $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }

    /** {@inheritDoc} */
    protected function providerName(): string
    {
        return 'gemini';
    }

    /** {@inheritDoc} */
    public function ping(): void
    {
        $baseUrl = $this->baseUrl ?: 'https://generativelanguage.googleapis.com';
        $url = "{$baseUrl}/v1beta/models?key={$this->apiKey}";

        try {
            $response = Http::timeout(8)->get($url);
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
