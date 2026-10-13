<?php

namespace App\Modules\Portal\Support;

use Illuminate\Support\Carbon;

/** Gera um calendário iCalendar (RFC 5545) com os jogos: abre no Google Agenda, Apple Calendário e Outlook. */
final class IcsCalendar
{
    /**
     * @param  list<array{uid: string, start: Carbon, minutes: int, summary: string, location: ?string, url: string, description: ?string}>  $events
     */
    public static function render(string $name, array $events, ?Carbon $now = null): string
    {
        $stamp = ($now ?? now())->copy()->utc()->format('Ymd\THis\Z');
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Overlays//Portal//PT-BR', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', 'X-WR-CALNAME:'.self::escape($name)];

        foreach ($events as $event) {
            $start = $event['start']->copy()->utc();

            $lines = [...$lines,
                'BEGIN:VEVENT',
                'UID:'.$event['uid'],
                'DTSTAMP:'.$stamp,
                'DTSTART:'.$start->format('Ymd\THis\Z'),
                'DTEND:'.$start->copy()->addMinutes($event['minutes'])->format('Ymd\THis\Z'),
                'SUMMARY:'.self::escape($event['summary']),
            ];

            if ($event['location'] !== null && $event['location'] !== '') {
                $lines[] = 'LOCATION:'.self::escape($event['location']);
            }

            if ($event['description'] !== null && $event['description'] !== '') {
                $lines[] = 'DESCRIPTION:'.self::escape($event['description']);
            }

            $lines[] = 'URL:'.$event['url'];
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(self::fold(...), $lines))."\r\n";
    }

    /** Vírgula, ponto e vírgula, barra invertida e quebras de linha têm sentido próprio no formato. */
    public static function escape(string $text): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\;', '\,', '\n', '\n', '\n'], $text);
    }

    /** Linhas passam de 75 bytes só dobradas (continuação começa com um espaço). */
    private static function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $parts = [];

        while (strlen($line) > 75) {
            $cut = 75;

            // Não corta no meio de um caractere UTF-8.
            while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
                $cut--;
            }

            $parts[] = substr($line, 0, $cut);
            $line = ' '.substr($line, $cut);
        }

        $parts[] = $line;

        return implode("\r\n", $parts);
    }
}
