<?php

namespace App\Services\Mail;

use App\Contracts\Mail\MailReceiver;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Crea la implementación de MailReceiver indicada en
 * config/helpdesk_mail.php → 'inbound'.
 */
class MailReceiverFactory
{
    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function make(?string $driver = null): MailReceiver
    {
        $driver = $driver ?: (string) config('helpdesk_mail.inbound.driver', 'none');
        $config = config("helpdesk_mail.inbound.drivers.{$driver}");

        if (!is_array($config) || empty($config['class'])) {
            $available = implode(', ', $this->availableDrivers());
            throw new InvalidArgumentException("El driver de recepción [{$driver}] no está definido. Disponibles: [{$available}].");
        }

        $class = $config['class'];
        unset($config['class']);

        // Opciones comunes a todos los drivers (cada driver puede sobrescribirlas).
        $config = array_merge([
            'name'           => $driver,
            'lookback_days'  => config('helpdesk_mail.inbound.lookback_days'),
            'subject_filter' => config('helpdesk_mail.inbound.subject_filter'),
            'mark_as_read'   => config('helpdesk_mail.inbound.mark_as_read'),
        ], $config);

        $receiver = $this->container->make($class, ['config' => $config]);

        if (!$receiver instanceof MailReceiver) {
            throw new InvalidArgumentException("La clase [{$class}] debe implementar " . MailReceiver::class . '.');
        }

        return $receiver;
    }

    /**
     * @return string[]
     */
    public function availableDrivers(): array
    {
        return array_keys((array) config('helpdesk_mail.inbound.drivers', []));
    }
}
