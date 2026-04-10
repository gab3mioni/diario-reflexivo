<?php

namespace App\Events;

use App\Models\DiaryAnalysis;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento emitido quando uma análise de diário é atualizada.
 */
class DiaryAnalysisUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Cria uma nova instância do evento.
     *
     * @param  \App\Models\DiaryAnalysis  $analysis  Análise de diário atualizada.
     */
    public function __construct(public readonly DiaryAnalysis $analysis)
    {
    }

    /**
     * Retorna os canais de broadcast do evento.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $analysis = $this->analysis->loadMissing('lessonResponse.lesson.subject');
        $teacherId = $analysis->lessonResponse?->lesson?->subject?->teacher_id;

        $channels = [
            new PrivateChannel("lesson-response.{$this->analysis->lesson_response_id}"),
        ];

        if ($teacherId) {
            $channels[] = new PrivateChannel("teacher.{$teacherId}");
        }

        return $channels;
    }

    /**
     * Retorna o payload mínimo para o broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'analysis_id' => $this->analysis->id,
            'lesson_response_id' => $this->analysis->lesson_response_id,
            'status' => $this->analysis->status,
        ];
    }

    /**
     * Retorna o nome do evento para o broadcast.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'diary-analysis.updated';
    }
}
