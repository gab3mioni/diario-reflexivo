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
 * Queued job that sends an email via an EmailNotificationInterface implementation.
 */
class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        private readonly EmailNotificationInterface $notification
    ) {}

    /**
     * Send the email to the notification recipient.
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
     * Handle job failure after all retries are exhausted.
     *
     * @param  \Throwable  $e  The exception that caused the failure
     */
    public function failed(\Throwable $e): void
    {
        Log::error('SendEmailJob failed after all retries', [
            'error' => $e->getMessage(),
            'exception' => $e::class,
        ]);
    }
}