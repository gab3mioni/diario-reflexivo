<?php

namespace App\Contracts;

use Illuminate\Mail\Mailable;

/**
 * Contrato para objetos de notificação por e-mail que fornecem dados do destinatário e uma instância Mailable.
 */
interface EmailNotificationInterface
{
    /**
     * Retorna o endereço de e-mail do destinatário.
     *
     * @return string
     */
    public function getRecipientEmail(): string;

    /**
     * Retorna o nome de exibição do destinatário.
     *
     * @return string
     */
    public function getRecipientName(): string;

    /**
     * Constrói e retorna a instância Mailable para esta notificação.
     *
     * @return Mailable
     */
    public function getMailable(): Mailable;
}