<?php

namespace App\Notifications\Email;

use App\Contracts\EmailNotificationInterface;
use App\Mail\StudentWelcomeMail;
use App\Models\User;
use Illuminate\Mail\Mailable;

class StudentWelcomeNotification implements EmailNotificationInterface
{
    public function __construct(
        private readonly User $student,
        private readonly string $plainPassword,
    ) {}

    public function getRecipientEmail(): string
    {
        return $this->student->email;
    }

    public function getRecipientName(): string
    {
        return $this->student->name;
    }

    public function getMailable(): Mailable
    {
        return new StudentWelcomeMail(
            studentName: $this->student->name,
            studentEmail: $this->student->email,
            plainPassword: $this->plainPassword,
            loginUrl: route('login'),
        );
    }
}
