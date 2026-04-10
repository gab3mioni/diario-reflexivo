<?php

namespace App\Services\AiProviders;

use App\Exceptions\AiProviderException;
use App\Models\AiProviderConfig;

/**
 * Classe abstrata base para provedores de IA que processam análises de diário.
 *
 * Cada provedor concreto implementa os métodos de construção de payload,
 * envio de requisição e extração de conteúdo específicos à sua API.
 */
abstract class AiProvider
{
    /**
     * @param  string   $model        Identificador do modelo de IA.
     * @param  float    $temperature  Temperatura para geração de texto.
     * @param  ?string  $apiKey       Chave de autenticação da API.
     * @param  ?string  $baseUrl      URL base do provedor (null usa o padrão).
     */
    public function __construct(
        protected string $model,
        protected float $temperature,
        protected ?string $apiKey,
        protected ?string $baseUrl,
    ) {}

    /**
     * Cria uma instância do provedor adequado a partir da configuração persistida.
     *
     * @param  AiProviderConfig  $config  Configuração do provedor.
     * @return static
     *
     * @throws \InvalidArgumentException  Se o provedor configurado for desconhecido.
     */
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
     * Envia um prompt de sistema e conteúdo do usuário à IA e retorna o JSON parseado.
     *
     * @param  string  $systemPrompt  Prompt de sistema enviado à IA.
     * @param  string  $userContent   Conteúdo do usuário a ser analisado.
     * @return array<string, mixed>  Resposta JSON decodificada da IA.
     *
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
     * Constrói o payload de requisição específico do provedor.
     *
     * @param  string  $systemPrompt  Prompt de sistema.
     * @param  string  $userContent   Conteúdo do usuário.
     * @return array<string, mixed>
     */
    abstract protected function buildRequestPayload(string $systemPrompt, string $userContent): array;

    /**
     * Envia a requisição HTTP e retorna o corpo da resposta decodificado.
     *
     * @param  array<string, mixed>  $payload  Payload da requisição.
     * @return array<string, mixed>
     *
     * @throws AiProviderException
     */
    abstract protected function sendRequest(array $payload): array;

    /**
     * Extrai o conteúdo de texto do formato de resposta específico do provedor.
     *
     * @param  array<string, mixed>  $responseData  Corpo da resposta decodificado.
     * @return string
     */
    abstract protected function extractContent(array $responseData): string;

    /**
     * Retorna o nome do provedor para mensagens de erro.
     *
     * @return string
     */
    abstract protected function providerName(): string;

    /**
     * Realiza uma verificação leve de conectividade/autenticação com o provedor.
     *
     * Não deve consumir tokens de geração. Retorna void em caso de sucesso
     * e lança AiProviderException em qualquer falha (auth, rede, non-2xx).
     *
     * @return void
     *
     * @throws AiProviderException
     */
    abstract public function ping(): void;

    /**
     * Decodifica a resposta de texto da IA como JSON, removendo cercas de código markdown.
     *
     * @param  string  $content  Conteúdo de texto retornado pela IA.
     * @return array<string, mixed>
     *
     * @throws AiProviderException  Se o JSON for inválido.
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
