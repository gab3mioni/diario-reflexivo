<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StudentWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $studentName,
        public string $studentEmail,
        public string $plainPassword,
        public string $loginUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Bem-vindo(a) ao Diário Reflexivo');
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.student-welcome',
            with: [
                'name' => $this->studentName,
                'email' => $this->studentEmail,
                'password' => $this->plainPassword,
                'loginUrl' => $this->loginUrl,
            ],
        );
    }
}
