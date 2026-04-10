<?php

namespace App\Notifications;

use App\Models\ResponseAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Notificação enviada quando um alerta de resposta é criado.
 */
class ResponseAlertRaised extends Notification
{
    use Queueable;

    /**
     * Cria uma nova instância da notificação.
     *
     * @param  \App\Models\ResponseAlert  $alert  Alerta de resposta associado.
     */
    public function __construct(public readonly ResponseAlert $alert)
    {
    }

    /**
     * Retorna os canais de entrega da notificação.
     *
     * @param  object  $notifiable  Entidade que receberá a notificação.
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Retorna os dados para armazenamento na base de dados.
     *
     * @param  object  $notifiable  Entidade que receberá a notificação.
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->payload();
    }

    /**
     * Retorna a mensagem de broadcast da notificação.
     *
     * @param  object  $notifiable  Entidade que receberá a notificação.
     * @return \Illuminate\Notifications\Messages\BroadcastMessage
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload());
    }

    /**
     * Retorna o tipo do evento de broadcast.
     *
     * @return string
     */
    public function broadcastType(): string
    {
        return 'response-alert.raised';
    }

    /**
     * Monta o payload com os dados do alerta para entrega.
     *
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
