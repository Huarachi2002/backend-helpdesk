<?php

namespace App\Services\Mail\Receivers;

use App\Contracts\Mail\MailReceiver;
use App\Services\Mail\IncomingMail;
use App\Services\Mail\MailAddress;
use Carbon\Carbon;
use RuntimeException;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message;

/**
 * Lee el buzón de la mesa de ayuda por IMAP. Sirve para Gmail / Google Workspace
 * (imap.gmail.com) y para cualquier servidor propio del cliente con IMAP
 * (Dovecot, Exchange, Zimbra, cPanel...). Solo cambia la configuración.
 *
 * Usa webklex/php-imap, que implementa el protocolo en PHP puro
 * (no requiere la extensión ext-imap).
 */
class ImapMailReceiver implements MailReceiver
{
    protected array $config;
    protected ?Client $client = null;

    /** @var array<string, Message> uid => mensaje leído (para marcarlo luego) */
    protected array $messages = [];

    public function __construct(array $config = [])
    {
        $config = array_filter($config, fn ($v) => $v !== null && $v !== '');
        $this->config = array_merge($this->defaults(), $config);
    }

    protected function defaults(): array
    {
        return [
            'name'           => 'imap',
            'port'           => 993,
            'encryption'     => 'ssl',
            'validate_cert'  => true,
            'folder'         => 'INBOX',
            'timeout'        => 30,
            'lookback_days'  => 3,
            'subject_filter' => 'TCK-',
            'mark_as_read'   => true,
        ];
    }

    public function driverName(): string
    {
        return (string) $this->config['name'];
    }

    public function mailboxAddress(): ?string
    {
        foreach ([$this->config['address'] ?? null, $this->config['username'] ?? null] as $candidate) {
            if ($candidate && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                return $candidate;
            }
        }

        return null;
    }

    public function fetch(int $limit = 50): array
    {
        $folderPath = (string) $this->config['folder'];
        $folder = $this->client()->getFolderByPath($folderPath);
        if (!$folder) {
            throw new RuntimeException("La carpeta IMAP [{$folderPath}] no existe en el buzón.");
        }

        $query = $folder->query()
            ->leaveUnread()
            ->setFetchOrder('asc')
            ->whereSince(Carbon::now()->subDays(max(1, (int) $this->config['lookback_days'])));

        if (!empty($this->config['subject_filter'])) {
            $query->whereSubject((string) $this->config['subject_filter']);
        }

        $result = [];
        foreach ($query->limit(max(1, $limit))->get() as $message) {
            $incoming = self::toIncomingMail($message);
            $this->messages[$incoming->uid] = $message;
            $result[] = $incoming;
        }

        return $result;
    }

    public function markProcessed(IncomingMail $mail): void
    {
        if (empty($this->config['mark_as_read']) || !isset($this->messages[$mail->uid])) {
            return;
        }

        $this->messages[$mail->uid]->setFlag('Seen');
    }

    /**
     * Convierte un mensaje de webklex al DTO de la aplicación.
     * Público para poder probarlo con Message::fromString() sin servidor.
     */
    public static function toIncomingMail(Message $message): IncomingMail
    {
        try {
            $uid = (string) $message->getUid();
        } catch (\Throwable $e) {
            $uid = '';
        }

        $fromAttr = $message->getFrom();
        $fromAddr = $fromAttr ? $fromAttr->first() : null;
        $from = $fromAddr && !empty($fromAddr->mail)
            ? new MailAddress((string) $fromAddr->mail, $fromAddr->personal ? (string) $fromAddr->personal : null)
            : null;

        $references = $message->getReferences();
        $references = $references ? $references->toArray() : [];
        // Algunos servidores entregan References como un solo string separado por espacios.
        $references = preg_split('/[\s,]+/', implode(' ', array_map('strval', $references)), -1, PREG_SPLIT_NO_EMPTY);

        $dateAttr = $message->getDate();
        $date = $dateAttr ? $dateAttr->first() : null;

        $header = $message->getHeader();

        return new IncomingMail(
            $uid,
            self::attrString($message->getMessageId()),
            self::attrString($message->getInReplyTo()),
            $references,
            $from,
            self::attrString($message->getSubject()) ?? '',
            $message->hasTextBody() ? $message->getTextBody() : '',
            $message->hasHTMLBody() ? $message->getHTMLBody() : '',
            $date instanceof \DateTimeInterface ? $date : null,
            $header ? (string) $header->raw : ''
        );
    }

    protected static function attrString($attribute): ?string
    {
        if ($attribute === null) {
            return null;
        }
        $value = is_object($attribute) && method_exists($attribute, 'first') ? $attribute->first() : $attribute;

        return $value === null ? null : (string) $value;
    }

    protected function client(): Client
    {
        if ($this->client) {
            return $this->client;
        }

        if (empty($this->config['host']) || empty($this->config['username']) || empty($this->config['password'])) {
            throw new RuntimeException('El buzón IMAP no está configurado (host, usuario o contraseña vacíos).');
        }

        $manager = new ClientManager([
            'options' => [
                // Nunca marcar como leído al descargar; solo con markProcessed().
                'fetch' => \Webklex\PHPIMAP\IMAP::FT_PEEK,
                'soft_fail' => true,
            ],
        ]);

        $encryption = $this->config['encryption'];
        $this->client = $manager->make([
            'host'          => $this->config['host'],
            'port'          => (int) $this->config['port'],
            'encryption'    => in_array($encryption, ['none', 'false', false], true) ? false : $encryption,
            'validate_cert' => filter_var($this->config['validate_cert'], FILTER_VALIDATE_BOOLEAN),
            'username'      => $this->config['username'],
            'password'      => $this->config['password'],
            'protocol'      => 'imap',
            'timeout'       => (int) $this->config['timeout'],
        ]);
        $this->client->connect();

        return $this->client;
    }

    public function __destruct()
    {
        try {
            $this->client?->disconnect();
        } catch (\Throwable $e) {
            // Conexión ya cerrada.
        }
    }
}
