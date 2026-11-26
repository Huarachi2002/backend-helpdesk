<?php

namespace App\Services\Mail;

/**
 * Extrae solo el texto nuevo de una respuesta por correo, quitando el mensaje
 * citado ("El lun., 24 nov. ... escribió:"), firmas estándar y la línea marcador
 * que el sistema incluye en sus correos.
 */
class ReplyTextExtractor
{

    public function extract(?string $text, ?string $html = null): string
    {
        $body = trim((string) $text) !== '' ? (string) $text : $this->htmlToText((string) $html);
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        $lines = explode("\n", $body);

        $out = [];
        $count = count($lines);
        for ($i = 0; $i < $count; $i++) {
            $line = $lines[$i];
            $trimmed = trim($line);

            if ($this->startsQuote($trimmed, $lines, $i)) {
                break;
            }
            if (str_starts_with($trimmed, '>')) {
                continue;
            }
            $out[] = rtrim($line);
        }

        $result = trim(implode("\n", $out));

        return (string) preg_replace("/\n{3,}/", "\n\n", $result);
    }

    protected function startsQuote(string $line, array $lines, int $i): bool
    {
        if ($line === '') {
            return false;
        }

        if (str_contains($line, '##-') && stripos($line, 'esta línea') !== false) {
            return true;
        }

        // Delimitador estándar de firma.
        if ($line === '--') {
            return true;
        }

        // Gmail / Apple Mail / Thunderbird: "El ... escribió:" / "On ... wrote:".
        // Gmail suele partir esta línea en dos, por eso se mira también la siguiente.
        if (preg_match('/^(El|On|Le|Am)\s/iu', $line)) {
            $joined = $line . ' ' . trim($lines[$i + 1] ?? '');
            if (preg_match('/(escribi[oó]|wrote|a [ée]crit|schrieb)\s*:\s*$/iu', $line)
                || preg_match('/(escribi[oó]|wrote|a [ée]crit|schrieb)\s*:\s*$/iu', $joined)) {
                return true;
            }
        }

        // Outlook: "-----Mensaje original-----" o una línea de guiones bajos.
        if (preg_match('/^-{2,}\s*(original message|mensaje original)\s*-{2,}$/i', $line)
            || preg_match('/^_{10,}$/', $line)) {
            return true;
        }

        // Outlook: bloque "De: ... / Enviado: ... / Para: ...".
        if (preg_match('/^\*?(De|From)\s*:\*?\s*\S/iu', $line)) {
            for ($k = $i + 1; $k <= $i + 4 && isset($lines[$k]); $k++) {
                if (preg_match('/^\*?(Enviado|Sent|Fecha|Date|Para|To)\s*:/iu', trim($lines[$k]))) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function htmlToText(string $html): string
    {
        if ($html === '') {
            return '';
        }

        // Quitar los bloques citados que agregan Gmail / Outlook / Apple Mail.
        $html = preg_replace('/<div[^>]+class="[^"]*gmail_quote[^"]*".*$/is', '', $html);
        $html = preg_replace('/<div[^>]+id="(divRplyFwdMsg|appendonsend)".*$/is', '', $html);
        $html = preg_replace('/<blockquote\b.*?<\/blockquote>/is', '', $html);
        $html = preg_replace('/<(style|script|head)\b.*?<\/\1>/is', '', $html);
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $html = preg_replace('/<\/(p|div|li|tr|h[1-6])>/i', "\n", $html);

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return (string) preg_replace('/[ \t]+/', ' ', $text);
    }
}
