<?php

use App\Services\Mail\Senders\GmailMailSender;
use App\Services\Mail\Senders\LogMailSender;
use App\Services\Mail\Senders\SmtpMailSender;

return [

    /*
    |--------------------------------------------------------------------------
    | Servidor de correo activo
    |--------------------------------------------------------------------------
    |
    | Define qué implementación de App\Contracts\Mail\MailSender se inyecta en
    | toda la aplicación. Cambiar de Gmail a un servidor propio del cliente es
    | solo cambiar esta variable (y sus credenciales), sin tocar código.
    |
    | Valores: "gmail", "smtp", "log"
    |
    */

    'driver' => env('HELPDESK_MAIL_DRIVER', 'smtp'),

    /*
    |--------------------------------------------------------------------------
    | Drivers disponibles
    |--------------------------------------------------------------------------
    |
    | 'class' es la implementación concreta. Para agregar otro proveedor
    | (ej. una API HTTP como SendGrid o Microsoft Graph) crea una clase que
    | implemente MailSender y regístrala aquí con su propia configuración.
    |
    */

    'drivers' => [

        // Servidor de Google (Gmail / Google Workspace) con contraseña de aplicación.
        'gmail' => [
            'class'        => GmailMailSender::class,
            'username'     => env('GMAIL_USERNAME'),
            'password'     => env('GMAIL_APP_PASSWORD'),
            'from_address' => env('GMAIL_FROM_ADDRESS', env('GMAIL_USERNAME')),
            'from_name'    => env('MAIL_FROM_NAME', 'Mesa de Ayuda'),
        ],

        // Servidor SMTP propio administrado por el cliente (o cualquier SMTP estándar).
        // Si no se definen las variables HELPDESK_SMTP_*, se usan las MAIL_* de Laravel.
        'smtp' => [
            'class'        => SmtpMailSender::class,
            'host'         => env('HELPDESK_SMTP_HOST', env('MAIL_HOST')),
            'port'         => env('HELPDESK_SMTP_PORT', env('MAIL_PORT', 587)),
            'encryption'   => env('HELPDESK_SMTP_ENCRYPTION', env('MAIL_ENCRYPTION', 'tls')),
            'username'     => env('HELPDESK_SMTP_USERNAME', env('MAIL_USERNAME')),
            'password'     => env('HELPDESK_SMTP_PASSWORD', env('MAIL_PASSWORD')),
            'timeout'      => env('HELPDESK_SMTP_TIMEOUT', 30),
            'from_address' => env('HELPDESK_SMTP_FROM_ADDRESS', env('MAIL_FROM_ADDRESS')),
            'from_name'    => env('MAIL_FROM_NAME', 'Mesa de Ayuda'),
        ],

        // Solo registra los correos en storage/logs (desarrollo / pruebas).
        'log' => [
            'class'   => LogMailSender::class,
            'channel' => env('HELPDESK_MAIL_LOG_CHANNEL'),
        ],
    ],

];
