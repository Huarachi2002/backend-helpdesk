<?php

namespace App\Providers;

use App\Contracts\Mail\MailReceiver;
use App\Contracts\Mail\MailSender;
use App\Services\Mail\MailReceiverFactory;
use App\Services\Mail\MailSenderFactory;
use Illuminate\Support\ServiceProvider;

/**
 * Registra en el contenedor de dependencias qué servidor de correo se usa.
 *
 * - MailSender  (envío):    HELPDESK_MAIL_DRIVER     → gmail | smtp | log
 * - MailReceiver (recepción): HELPDESK_INBOUND_DRIVER → gmail | imap | none
 *
 * Cualquier clase que pida estas interfaces en su constructor recibe la
 * implementación configurada, sin saber si es Gmail o un servidor propio.
 */
class MailServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(MailSenderFactory::class);
        $this->app->singleton(MailReceiverFactory::class);

        $this->app->singleton(MailSender::class, function ($app) {
            return $app->make(MailSenderFactory::class)->make(config('helpdesk_mail.driver'));
        });

        // El receptor no se conecta al construirse (la conexión IMAP es diferida),
        // así que inyectarlo solo para conocer su dirección es barato.
        $this->app->singleton(MailReceiver::class, function ($app) {
            return $app->make(MailReceiverFactory::class)->make(config('helpdesk_mail.inbound.driver'));
        });
    }
}
