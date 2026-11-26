<?php

namespace App\Console\Commands;

use App\Contracts\Mail\MailReceiver;
use App\Services\InboundMailProcessor;
use App\Services\Mail\InboundMailResult;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Lee el buzón de la mesa de ayuda y registra en cada ticket las respuestas que
 * los usuarios enviaron desde su correo. Se programa cada minuto (ver Kernel).
 */
class ProcesarCorreosEntrantesCommand extends Command
{
    protected $signature = 'tickets:procesar-correos
                            {--limit= : Máximo de correos a revisar en esta ejecución}
                            {--dry-run : Solo muestra qué haría, sin registrar ni marcar nada}';

    protected $description = 'Registra en los tickets las respuestas recibidas por correo (buzón IMAP).';

    public function handle(MailReceiver $receiver, InboundMailProcessor $processor): int
    {
        if ($receiver->mailboxAddress() === null) {
            $this->warn('La recepción de correo está desactivada (HELPDESK_INBOUND_DRIVER=none).');
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $limit = (int) ($this->option('limit') ?: config('helpdesk_mail.inbound.batch_limit', 50));
        $ttl = now()->addDays((int) config('helpdesk_mail.inbound.lookback_days', 3) + 1);

        try {
            $mails = $receiver->fetch($limit);
        } catch (\Throwable $e) {
            $message = "No se pudo leer el buzón ({$receiver->driverName()}): " . $e->getMessage();
            Log::error($message);
            $this->error($message);
            return self::FAILURE;
        }

        $rows = [];
        $counts = array_fill_keys([
            InboundMailResult::REGISTERED, InboundMailResult::DUPLICATE, InboundMailResult::MATCHED,
            InboundMailResult::IGNORED, InboundMailResult::REJECTED, 'skipped',
        ], 0);

        foreach ($mails as $mail) {
            $cacheKey = 'helpdesk:inbound:done:' . sha1($mail->dedupeKey());

            // Correos ya evaluados que no generaron registro (no se vuelven a procesar).
            if (!$dryRun && Cache::has($cacheKey)) {
                $counts['skipped']++;
                continue;
            }

            try {
                $result = $processor->process($mail, $dryRun);
            } catch (\Throwable $e) {
                // Error inesperado (ej. base de datos): se reintenta en la próxima ejecución.
                Log::error("Error al procesar el correo {$mail->messageId}: " . $e->getMessage());
                $this->error("Error con \"{$mail->subject}\": " . $e->getMessage());
                continue;
            }

            $counts[$result->status]++;

            if (!$dryRun) {
                if ($result->isRegistered()) {
                    $receiver->markProcessed($mail);
                } else {
                    Cache::put($cacheKey, $result->status, $ttl);
                }

                if ($result->status === InboundMailResult::REJECTED) {
                    Log::notice("Correo entrante rechazado de {$mail->from?->email} ({$mail->subject}): {$result->reason}");
                } elseif ($result->status === InboundMailResult::REGISTERED) {
                    Log::info("Respuesta por correo registrada en {$result->ticket?->codigo} de {$mail->from?->email}.");
                }
            }

            $rows[] = [
                $mail->date ? $mail->date->format('d/m H:i') : '-',
                $mail->from->email ?? '-',
                mb_strimwidth($mail->subject, 0, 45, '…'),
                $result->ticket->codigo ?? '-',
                $result->status,
                $result->oldStatus && $result->newStatus && $result->oldStatus !== $result->newStatus
                    ? "{$result->reason} ({$result->oldStatus} → {$result->newStatus})"
                    : $result->reason,
            ];
        }

        if ($rows) {
            $this->table(['Fecha', 'De', 'Asunto', 'Ticket', 'Resultado', 'Detalle'], $rows);
        }

        $summary = collect($counts)->filter()->map(fn ($n, $k) => "{$k}: {$n}")->implode(', ');
        $this->info(sprintf(
            '%sBuzón %s (%s): %d correo(s) revisado(s). %s',
            $dryRun ? '[dry-run] ' : '',
            $receiver->mailboxAddress(),
            $receiver->driverName(),
            count($mails),
            $summary
        ));

        return self::SUCCESS;
    }
}
