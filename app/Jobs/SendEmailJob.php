<?php

namespace App\Jobs;

use App\Contracts\EmailNotificationInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Job assíncrono que envia um email através de uma implementação de EmailNotificationInterface.
 */
class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Número máximo de tentativas. */
    public int $tries = 3;

    /** Intervalo em segundos entre tentativas. */
    public int $backoff = 60;

    /**
     * Cria uma nova instância do job.
     *
     * @param  \App\Contracts\EmailNotificationInterface  $notification  Notificação com dados do email.
     */
    public function __construct(
        private readonly EmailNotificationInterface $notification
    ) {}

    /**
     * Envia o email ao destinatário definido na notificação.
     *
     * @return void
     */
    public function handle(): void
    {
        $email = $this->notification->getRecipientEmail();
        $name = $this->notification->getRecipientName();
        $mailable = $this->notification->getMailable();

        Mail::to($email, $name)->send($mailable);

        Log::info('Email sent successfully', [
            'recipient' => $email,
            'mailable' => $mailable::class,
        ]);
    }

    /**
     * Trata a falha definitiva do job após esgotamento das tentativas.
     *
     * @param  \Throwable  $e  Exceção que causou a falha.
     * @return void
     */
    public function failed(\Throwable $e): void
    {
        Log::error('SendEmailJob failed after all retries', [
            'error' => $e->getMessage(),
            'exception' => $e::class,
        ]);
    }
}