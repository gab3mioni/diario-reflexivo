<?php

namespace App\Support;

use App\Models\LessonResponse;
use Illuminate\Support\Str;

final class LogContext
{
    public static function traceId(): string
    {
        $header = request()?->header('X-Trace-ID');
        if (is_string($header) && $header !== '') {
            return $header;
        }

        return (string) Str::uuid();
    }

    /** @return array<string, mixed> */
    public static function chat(LessonResponse $response): array
    {
        return [
            'trace_id' => self::traceId(),
            'response_id' => $response->id,
            'student_id' => $response->student_id,
            'lesson_id' => $response->lesson_id,
        ];
    }

    /** @return array<string, mixed> */
    public static function user(): array
    {
        $user = auth()->user();

        return [
            'trace_id' => self::traceId(),
            'user_id' => $user?->id,
        ];
    }
}
