<?php

namespace App\Events;

use App\Models\LessonResponse;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LessonResponseSubmitted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly LessonResponse $response)
    {
    }

    /**
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

    public function broadcastAs(): string
    {
        return 'lesson-response.submitted';
    }

    /**
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
