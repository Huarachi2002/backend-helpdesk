<?php

use App\Services\Mail\Receivers\ImapMailReceiver;
use App\Services\Mail\Receivers\NullMailReceiver;
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

    /*
    |--------------------------------------------------------------------------
    | Recepción de respuestas por correo (buzón de la mesa de ayuda)
    |--------------------------------------------------------------------------
    |
    | Implementación de App\Contracts\Mail\MailReceiver que se inyecta. El comando
    | `php artisan tickets:procesar-correos` (programado cada minuto) lee el buzón
    | y registra en el ticket las respuestas que los usuarios envían desde su
    | correo. Con un driver activo, los correos del sistema llevan Reply-To a este
    | buzón para que las respuestas lleguen aquí.
    |
    | Valores: "gmail", "imap", "none" (desactivado)
    |
    */

    'inbound' => [

        'driver' => env('HELPDESK_INBOUND_DRIVER', 'none'),

        // Solo se revisan correos de los últimos N días...
        'lookback_days' => (int) env('HELPDESK_INBOUND_LOOKBACK_DAYS', 3),

        // ...cuyo asunto contenga este texto (los correos del sistema llevan el
        // código del ticket, ej. "Re: [TCK-008] ..."). Así nunca se tocan otros
        // correos del buzón. Vacío = revisar todos.
        'subject_filter' => env('HELPDESK_INBOUND_SUBJECT_FILTER', 'TCK-'),

        // Marcar como leídas en el buzón las respuestas registradas.
        'mark_as_read' => filter_var(env('HELPDESK_INBOUND_MARK_AS_READ', true), FILTER_VALIDATE_BOOLEAN),

        // Máximo de correos por ejecución.
        'batch_limit' => (int) env('HELPDESK_INBOUND_BATCH_LIMIT', 50),

        'drivers' => [

            // Buzón de Gmail / Google Workspace (IMAP con contraseña de aplicación).
            // Si no se definen GMAIL_*, usa la misma cuenta del envío (MAIL_*).
            'gmail' => [
                'class'    => ImapMailReceiver::class,
                'host'     => 'imap.gmail.com',
                'port'     => 993,
                'encryption' => 'ssl',
                'username' => env('GMAIL_USERNAME', env('MAIL_USERNAME')),
                'password' => env('GMAIL_APP_PASSWORD', env('MAIL_PASSWORD')),
                'address'  => env('HELPDESK_INBOUND_ADDRESS'),
                'folder'   => env('HELPDESK_IMAP_FOLDER', 'INBOX'),
            ],

            // Servidor IMAP propio administrado por el cliente.
            // Usuario y contraseña caen a los del SMTP si no se definen.
            'imap' => [
                'class'         => ImapMailReceiver::class,
                'host'          => env('HELPDESK_IMAP_HOST'),
                'port'          => env('HELPDESK_IMAP_PORT', 993),
                'encryption'    => env('HELPDESK_IMAP_ENCRYPTION', 'ssl'),
                'validate_cert' => env('HELPDESK_IMAP_VALIDATE_CERT', true),
                'username'      => env('HELPDESK_IMAP_USERNAME', env('HELPDESK_SMTP_USERNAME', env('MAIL_USERNAME'))),
                'password'      => env('HELPDESK_IMAP_PASSWORD', env('HELPDESK_SMTP_PASSWORD', env('MAIL_PASSWORD'))),
                'address'       => env('HELPDESK_INBOUND_ADDRESS'),
                'folder'        => env('HELPDESK_IMAP_FOLDER', 'INBOX'),
            ],

            // Recepción desactivada.
            'none' => [
                'class' => NullMailReceiver::class,
            ],
        ],
    ],

];
