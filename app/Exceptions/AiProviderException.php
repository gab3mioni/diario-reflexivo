<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Exceção lançada quando ocorre um erro relacionado a provedores de IA.
 *
 * Fornece métodos de fábrica estáticos para criar instâncias com códigos de erro padronizados,
 * facilitando o tratamento e a tradução de mensagens.
 */
class AiProviderException extends RuntimeException
{
    /** Código: falha na requisição ao provedor. */
    public const ERROR_REQUEST_FAILED = 'ai.request_failed';

    /** Código: resposta JSON inválida do provedor. */
    public const ERROR_INVALID_JSON = 'ai.invalid_json';

    /** Código: nenhum provedor de IA ativo configurado. */
    public const ERROR_NO_PROVIDER = 'ai.no_provider';

    /** Código: nenhum prompt de análise ativo configurado. */
    public const ERROR_NO_PROMPT = 'ai.no_prompt';

    /** Código: limite de requisições excedido. */
    public const ERROR_RATE_LIMITED = 'ai.rate_limited';

    /** Código: erro no classificador de ramificação. */
    public const ERROR_CLASSIFIER = 'ai.classifier_error';

    /** @var string Código de erro padronizado para identificação programática. */
    public readonly string $errorCode;

    /**
     * @param  string      $message    Mensagem descritiva do erro.
     * @param  string      $errorCode  Código padronizado do erro.
     * @param  int         $code       Código numérico da exceção.
     * @param  ?\Throwable $previous   Exceção anterior na cadeia.
     */
    public function __construct(string $message, string $errorCode = '', int $code = 0, ?\Throwable $previous = null)
    {
        $this->errorCode = $errorCode;
        parent::__construct($message, $code, $previous);
    }

    /**
     * Cria exceção para falha na requisição ao provedor de IA.
     *
     * @param  string  $provider  Nome do provedor (ex.: 'openai', 'gemini').
     * @param  string  $message   Mensagem de erro retornada pelo provedor.
     * @return self
     */
    public static function requestFailed(string $provider, string $message): self
    {
        return new self("AI provider [{$provider}] request failed: {$message}", self::ERROR_REQUEST_FAILED);
    }

    /**
     * Cria exceção para resposta JSON inválida do provedor.
     *
     * @param  string  $provider    Nome do provedor.
     * @param  string  $rawContent  Conteúdo bruto retornado pelo provedor.
     * @return self
     */
    public static function invalidJson(string $provider, string $rawContent): self
    {
        return new self("AI provider [{$provider}] returned invalid JSON: {$rawContent}", self::ERROR_INVALID_JSON);
    }

    /**
     * Cria exceção quando nenhum provedor de IA está ativo.
     *
     * @return self
     */
    public static function noActiveProvider(): self
    {
        return new self('No active AI provider configured.', self::ERROR_NO_PROVIDER);
    }

    /**
     * Cria exceção quando nenhum prompt de análise está ativo.
     *
     * @return self
     */
    public static function noActivePrompt(): self
    {
        return new self('No active analysis prompt configured.', self::ERROR_NO_PROMPT);
    }

    /**
     * Cria exceção quando o limite de análises por janela de tempo é excedido.
     *
     * @param  int  $max  Número máximo de análises permitidas.
     * @return self
     */
    public static function rateLimitExceeded(int $max): self
    {
        return new self("Rate limit exceeded: maximum of {$max} analyses per window.", self::ERROR_RATE_LIMITED);
    }
}
