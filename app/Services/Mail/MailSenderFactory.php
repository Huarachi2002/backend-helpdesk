<?php

namespace App\Services\Mail;

use App\Contracts\Mail\MailSender;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Crea la implementación de MailSender indicada en config/helpdesk_mail.php.
 * Es el único lugar que conoce las clases concretas de cada servidor de correo.
 */
class MailSenderFactory
{
    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function make(?string $driver = null): MailSender
    {
        $driver = $driver ?: (string) config('helpdesk_mail.driver', 'smtp');
        $config = config("helpdesk_mail.drivers.{$driver}");

        if (!is_array($config) || empty($config['class'])) {
            $available = implode(', ', array_keys((array) config('helpdesk_mail.drivers', [])));
            throw new InvalidArgumentException("El driver de correo [{$driver}] no está definido. Disponibles: [{$available}].");
        }

        $class = $config['class'];
        unset($config['class']);
        $config['name'] = $config['name'] ?? $driver;

        $sender = $this->container->make($class, ['config' => $config]);

        if (!$sender instanceof MailSender) {
            throw new InvalidArgumentException("La clase [{$class}] debe implementar " . MailSender::class . '.');
        }

        return $sender;
    }

    /**
     * @return string[]
     */
    public function availableDrivers(): array
    {
        return array_keys((array) config('helpdesk_mail.drivers', []));
    }
}
