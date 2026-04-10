<?php

namespace App\Events;

use App\Models\LessonResponse;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento emitido quando uma resposta de aula é submetida pelo aluno.
 */
class LessonResponseSubmitted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Cria uma nova instância do evento.
     *
     * @param  \App\Models\LessonResponse  $response  Resposta de aula submetida.
     */
    public function __construct(public readonly LessonResponse $response)
    {
    }

    /**
     * Retorna os canais de broadcast do evento.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $teacherId = $this->response
            ->loadMissing('lesson.subject')
            ->lesson?->subject?->teacher_id;

        return $teacherId
            ? [new PrivateChannel("teacher.{$teacherId}")]
            : [];
    }

    /**
     * Retorna o nome do evento para o broadcast.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'lesson-response.submitted';
    }

    /**
     * Retorna o payload de dados para o broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'lesson_response_id' => $this->response->id,
            'lesson_id' => $this->response->lesson_id,
            'student_id' => $this->response->student_id,
            'submitted_at' => $this->response->submitted_at?->toISOString(),
        ];
    }
}
