<?php

namespace App\Providers;

use App\Contracts\Mail\MailSender;
use App\Services\Mail\MailSenderFactory;
use Illuminate\Support\ServiceProvider;

/**
 * Registra en el contenedor de dependencias qué servidor de correo se usa.
 *
 * Cualquier clase que pida `MailSender` en su constructor (MailService,
 * TicketReplyService, ...) recibirá la implementación configurada en
 * HELPDESK_MAIL_DRIVER, sin saber si es Gmail, SMTP propio o Log.
 */
class MailServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(MailSenderFactory::class);

        $this->app->singleton(MailSender::class, function ($app) {
            return $app->make(MailSenderFactory::class)->make(config('helpdesk_mail.driver'));
        });
    }
}
