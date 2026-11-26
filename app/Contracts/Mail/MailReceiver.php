<?php

namespace App\Contracts\Mail;

use App\Services\Mail\IncomingMail;

/**
 * Contrato del servicio de RECEPCIÓN de correo (buzón de la mesa de ayuda).
 *
 * Es la contraparte de MailSender: la aplicación solo conoce esta interfaz y la
 * implementación concreta (IMAP de Gmail, IMAP de un servidor propio, o ninguna)
 * se decide en App\Providers\MailServiceProvider según config/helpdesk_mail.php
 * → 'inbound.driver'. Para soportar otro origen (ej. webhooks de Mailgun o la API
 * de Microsoft Graph) basta con crear otra clase que implemente esta interfaz.
 */
interface MailReceiver
{
    /**
     * Correos recientes del buzón que podrían ser respuestas a tickets.
     * Leerlos NO los marca como leídos.
     *
     * @return IncomingMail[]
     */
    public function fetch(int $limit = 50): array;

    /**
     * Marca el correo como procesado en el servidor (ej. flag \Seen).
     */
    public function markProcessed(IncomingMail $mail): void;

    /**
     * Dirección del buzón que se lee. Se usa como Reply-To en los correos que
     * envía el sistema, para que las respuestas lleguen a este buzón.
     * Devuelve null si la recepción está desactivada.
     */
    public function mailboxAddress(): ?string;

    /**
     * Nombre del driver (ej. 'gmail', 'imap', 'none').
     */
    public function driverName(): string;
}
