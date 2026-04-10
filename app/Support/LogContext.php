<?php

namespace App\Support;

use App\Models\LessonResponse;
use Illuminate\Support\Str;

/**
 * Utilitário que fornece contexto padronizado para entradas de log.
 */
final class LogContext
{
    /**
     * Retorna o trace ID do cabeçalho da requisição ou gera um novo UUID.
     *
     * @return string
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
     * Retorna o contexto de log para operações de chat.
     *
     * @param  \App\Models\LessonResponse  $response  Resposta de aula associada.
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
     * Retorna o contexto de log para o utilizador autenticado.
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
