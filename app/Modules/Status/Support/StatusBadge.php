<?php

namespace App\Modules\Status\Support;

/** Selo de status em SVG (estilo "status | operacional"), para <img> em qualquer site. Textos fixos e escapados. */
class StatusBadge
{
    private const MESSAGES = ['operational' => 'operacional', 'degraded' => 'instabilidade', 'outage' => 'fora do ar', 'maintenance' => 'manutenção'];

    private const COLORS = ['operational' => '#059669', 'degraded' => '#d97706', 'outage' => '#dc2626', 'maintenance' => '#2563eb'];

    public static function svg(string $overall): string
    {
        $key = array_key_exists($overall, self::MESSAGES) ? $overall : 'operational';
        $label = 'status';
        $message = self::MESSAGES[$key];
        $labelWidth = 46;
        $messageWidth = (int) (mb_strlen($message) * 6.4 + 16);
        $width = $labelWidth + $messageWidth;
        $e = fn (string $value) => htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $color = self::COLORS[$key];
        $title = $e("{$label}: {$message}");

        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="20" role="img" aria-label="'.$title.'">'
            .'<title>'.$title.'</title>'
            .'<clipPath id="r"><rect width="'.$width.'" height="20" rx="3" fill="#fff"/></clipPath>'
            .'<g clip-path="url(#r)"><rect width="'.$labelWidth.'" height="20" fill="#555"/><rect x="'.$labelWidth.'" width="'.$messageWidth.'" height="20" fill="'.$color.'"/></g>'
            .'<g fill="#fff" text-anchor="middle" font-family="Verdana,Geneva,DejaVu Sans,sans-serif" font-size="11">'
            .'<text x="'.($labelWidth / 2).'" y="14">'.$e($label).'</text>'
            .'<text x="'.($labelWidth + $messageWidth / 2).'" y="14">'.$e($message).'</text></g></svg>';
    }
}
