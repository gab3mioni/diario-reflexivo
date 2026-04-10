<?php

namespace App\Events;

use App\Models\DiaryAnalysis;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DiaryAnalysisUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly DiaryAnalysis $analysis)
    {
    }

    /**
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
     * Payload mínimo — front faz reload do Inertia para pegar o resto.
     */
    public function broadcastWith(): array
    {
        return [
            'analysis_id' => $this->analysis->id,
            'lesson_response_id' => $this->analysis->lesson_response_id,
            'status' => $this->analysis->status,
        ];
    }

    public function broadcastAs(): string
    {
        return 'diary-analysis.updated';
    }
}
