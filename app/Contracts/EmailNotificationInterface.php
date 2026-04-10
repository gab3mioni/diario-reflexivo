<?php

namespace App\Contracts;

use Illuminate\Mail\Mailable;

/**
 * Contract for email notification objects that provide recipient data and a mailable instance.
 */
interface EmailNotificationInterface
{
    /**
     * Get the recipient's email address.
     */
    public function getRecipientEmail(): string;

    /**
     * Get the recipient's display name.
     */
    public function getRecipientName(): string;

    /**
     * Build and return the mailable instance for this notification.
     */
    public function getMailable(): Mailable;
}