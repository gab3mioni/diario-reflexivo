<?php

namespace App\Services\AiProviders;

use App\Exceptions\AiProviderException;
use App\Models\AiProviderConfig;

abstract class AiProvider
{
    public function __construct(
        protected string $model,
        protected float $temperature,
        protected ?string $apiKey,
        protected ?string $baseUrl,
    ) {}

    public static function fromConfig(AiProviderConfig $config): static
    {
        return match ($config->provider) {
            'openai' => new OpenAiProvider($config->model, $config->temperature, $config->api_key, $config->base_url),
            'gemini' => new GeminiProvider($config->model, $config->temperature, $config->api_key, $config->base_url),
            'ollama' => new OllamaProvider($config->model, $config->temperature, null, $config->base_url),
            default => throw new \InvalidArgumentException("Unknown AI provider: {$config->provider}"),
        };
    }

    /**
     * Send a system prompt + user content to the AI and return parsed JSON.
     *
     * @return array The parsed JSON response from the AI.
     * @throws AiProviderException
     */
    public function analyze(string $systemPrompt, string $userContent): array
    {
        $payload = $this->buildRequestPayload($systemPrompt, $userContent);
        $responseData = $this->sendRequest($payload);
        $content = $this->extractContent($responseData);

        return $this->parseJsonResponse($content);
    }

    /**
     * Build the provider-specific request payload.
     */
    abstract protected function buildRequestPayload(string $systemPrompt, string $userContent): array;

    /**
     * Send the HTTP request and return the decoded response body.
     *
     * @throws AiProviderException
     */
    abstract protected function sendRequest(array $payload): array;

    /**
     * Extract the text content from the provider-specific response format.
     */
    abstract protected function extractContent(array $responseData): string;

    /**
     * Return the provider name for error messages.
     */
    abstract protected function providerName(): string;

    /**
     * Parse the AI text response as JSON.
     *
     * @throws AiProviderException
     */
    protected function parseJsonResponse(string $content): array
    {
        // Strip markdown code fences if the model wraps the JSON
        $content = trim($content);
        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
            $content = preg_replace('/\s*```$/', '', $content);
        }

        $decoded = json_decode(trim($content), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw AiProviderException::invalidJson($this->providerName(), $content);
        }

        return $decoded;
    }
}
