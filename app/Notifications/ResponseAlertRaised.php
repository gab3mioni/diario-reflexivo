<?php

namespace App\Notifications;

use App\Models\ResponseAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ResponseAlertRaised extends Notification
{
    use Queueable;

    public function __construct(public readonly ResponseAlert $alert)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->payload();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload());
    }

    public function broadcastType(): string
    {
        return 'response-alert.raised';
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $this->alert->loadMissing('lessonResponse.student', 'lessonResponse.lesson');

        return [
            'alert_id' => $this->alert->id,
            'lesson_response_id' => $this->alert->lesson_response_id,
            'type' => $this->alert->type,
            'severity' => $this->alert->severity,
            'reason' => $this->alert->reason,
            'student_name' => $this->alert->lessonResponse?->student?->name,
            'lesson_title' => $this->alert->lessonResponse?->lesson?->title,
            'created_at' => $this->alert->created_at?->toISOString(),
        ];
    }
}
