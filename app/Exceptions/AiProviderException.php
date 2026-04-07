<?php

namespace App\Exceptions;

use RuntimeException;

class AiProviderException extends RuntimeException
{
    public static function requestFailed(string $provider, string $message): self
    {
        return new self("AI provider [{$provider}] request failed: {$message}");
    }

    public static function invalidJson(string $provider, string $rawContent): self
    {
        return new self("AI provider [{$provider}] returned invalid JSON: {$rawContent}");
    }

    public static function noActiveProvider(): self
    {
        return new self('No active AI provider configured.');
    }

    public static function noActivePrompt(): self
    {
        return new self('No active analysis prompt configured.');
    }

    public static function rateLimitExceeded(int $max): self
    {
        return new self("Rate limit exceeded: maximum of {$max} analyses per diary.");
    }
}
