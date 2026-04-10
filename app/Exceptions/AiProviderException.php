<?php

namespace App\Exceptions;

use RuntimeException;

class AiProviderException extends RuntimeException
{
    // Códigos padronizados — usáveis no flash message e na tradução futura.
    public const ERROR_REQUEST_FAILED = 'ai.request_failed';
    public const ERROR_INVALID_JSON = 'ai.invalid_json';
    public const ERROR_NO_PROVIDER = 'ai.no_provider';
    public const ERROR_NO_PROMPT = 'ai.no_prompt';
    public const ERROR_RATE_LIMITED = 'ai.rate_limited';
    public const ERROR_CLASSIFIER = 'ai.classifier_error';

    public readonly string $errorCode;

    public function __construct(string $message, string $errorCode = '', int $code = 0, ?\Throwable $previous = null)
    {
        $this->errorCode = $errorCode;
        parent::__construct($message, $code, $previous);
    }

    public static function requestFailed(string $provider, string $message): self
    {
        return new self("AI provider [{$provider}] request failed: {$message}", self::ERROR_REQUEST_FAILED);
    }

    public static function invalidJson(string $provider, string $rawContent): self
    {
        return new self("AI provider [{$provider}] returned invalid JSON: {$rawContent}", self::ERROR_INVALID_JSON);
    }

    public static function noActiveProvider(): self
    {
        return new self('No active AI provider configured.', self::ERROR_NO_PROVIDER);
    }

    public static function noActivePrompt(): self
    {
        return new self('No active analysis prompt configured.', self::ERROR_NO_PROMPT);
    }

    public static function rateLimitExceeded(int $max): self
    {
        return new self("Rate limit exceeded: maximum of {$max} analyses per window.", self::ERROR_RATE_LIMITED);
    }
}
