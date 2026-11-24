<?php

namespace App\Contracts\Mail;

use App\Services\Mail\MailSendResult;
use App\Services\Mail\OutgoingMail;

/**
 * Contrato del servicio de envío de correo.
 *
 * Toda la aplicación (notificaciones, respuestas a tickets, recordatorios)
 * depende SOLO de esta interfaz. La implementación concreta (Gmail, SMTP propio
 * del cliente, Log, o cualquier proveedor futuro) se decide en el contenedor de
 * dependencias (App\Providers\MailServiceProvider) a partir de config/helpdesk_mail.php.
 *
 * Para agregar un nuevo servidor basta con crear una clase que implemente esta
 * interfaz y registrarla en config/helpdesk_mail.php → 'drivers'.
 */
interface MailSender
{
    /**
     * Envía un correo. No debe lanzar excepciones por fallos de transporte:
     * los errores se reportan en el MailSendResult.
     */
    public function send(OutgoingMail $mail): MailSendResult;

    /**
     * Nombre del driver (ej. 'gmail', 'smtp', 'log'), útil para auditoría y logs.
     */
    public function driverName(): string;
}
