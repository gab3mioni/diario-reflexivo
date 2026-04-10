<?php

namespace App\Support;

use App\Models\LessonResponse;
use Illuminate\Support\Str;

/**
 * Helper para compor contexto estruturado para Log:: ... → reduz inconsistência
 * de chaves entre call-sites e adiciona trace_id automaticamente.
 *
 * Uso:
 *   Log::warning('Classifier failed', LogContext::chat($response) + ['error' => $e->getMessage()]);
 */
final class LogContext
{
    /**
     * Retorna um trace id estável para o request atual (ou gera um novo fora dele).
     */
    public static function traceId(): string
    {
        $header = request()?->header('X-Trace-ID');
        if (is_string($header) && $header !== '') {
            return $header;
        }

        return (string) Str::uuid();
    }

    /**
     * Contexto padrão para logs relacionados a uma resposta de diário / chat.
     *
     * @return array<string, mixed>
     */
    public static function chat(LessonResponse $response): array
    {
        return [
            'trace_id' => self::traceId(),
            'response_id' => $response->id,
            'student_id' => $response->student_id,
            'lesson_id' => $response->lesson_id,
        ];
    }

    /**
     * Contexto genérico com dados do usuário autenticado, útil para auditoria.
     *
     * @return array<string, mixed>
     */
    public static function user(): array
    {
        $user = auth()->user();

        return [
            'trace_id' => self::traceId(),
            'user_id' => $user?->id,
        ];
    }
}
