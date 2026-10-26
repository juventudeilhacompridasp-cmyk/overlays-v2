<?php

namespace App\Support\Uploads;

use InvalidArgumentException;

/**
 * Verificação dos arquivos pessoais enviados (documentos de inscrição, atestados, súmulas; docs/17 §5).
 * Camadas, todas parametrizadas em `platform.uploads.scan`: (1) PDF com conteúdo ativo (JavaScript, ação de abrir/lançar,
 * arquivo embutido); (2) código de servidor ou HTML/script escondido em imagem; (3) ClamAV opcional por `clamd` (INSTREAM).
 * Recusa com mensagem clara; erro do ClamAV só vai ao log quando `clamav.fail_closed` está desligado.
 */
final class UploadScanner
{
    /** Marcas de PDF que executam algo ou carregam outro arquivo. */
    private const PDF_ACTIVE = ['/JavaScript', '/JS ', '/JS(', '/Launch', '/EmbeddedFile', '/RichMedia', '/OpenAction', '/AA '];

    private const IMAGE_CODE = ['<?php', '<?=', '<script', '<svg', '<html', '#!/'];

    public static function enabled(): bool
    {
        return (bool) config('platform.uploads.scan.enabled', true);
    }

    /** @throws InvalidArgumentException */
    public static function check(string $content, string $mime): void
    {
        if (! self::enabled()) {
            return;
        }

        if ($mime === 'application/pdf' && (bool) config('platform.uploads.scan.block_pdf_active', true)) {
            $head = substr($content, 0, 8);
            $hit = self::contains($content, self::PDF_ACTIVE);

            if (! str_starts_with($head, '%PDF') || $hit !== null) {
                throw new InvalidArgumentException('Este PDF tem conteúdo ativo (scripts ou arquivos embutidos). Gere o PDF novamente, sem esses recursos, ou envie uma foto.');
            }
        }

        if (str_starts_with($mime, 'image/') && (bool) config('platform.uploads.scan.block_image_code', true) && self::contains($content, self::IMAGE_CODE, true) !== null) {
            throw new InvalidArgumentException('A imagem contém código embutido e foi recusada. Tire uma nova foto ou exporte a imagem novamente.');
        }

        self::clamav($content);
    }

    /** @param list<string> $needles */
    private static function contains(string $content, array $needles, bool $ignoreCase = false): ?string
    {
        foreach ($needles as $needle) {
            if (($ignoreCase ? stripos($content, $needle) : strpos($content, $needle)) !== false) {
                return $needle;
            }
        }

        return null;
    }

    private static function clamav(string $content): void
    {
        $host = trim((string) config('platform.uploads.scan.clamav.host', ''));

        if ($host === '') {
            return;
        }

        $port = max(1, (int) config('platform.uploads.scan.clamav.port', 3310));
        $failClosed = (bool) config('platform.uploads.scan.clamav.fail_closed', false);
        $socket = @fsockopen($host, $port, $errno, $error, max(1, (int) config('platform.uploads.scan.clamav.timeout', 5)));

        if ($socket === false) {
            self::unavailable($failClosed, "ClamAV indisponível: {$error}");

            return;
        }

        try {
            fwrite($socket, "zINSTREAM\0");

            foreach (str_split($content, 8192) as $chunk) {
                fwrite($socket, pack('N', strlen($chunk)).$chunk);
            }

            fwrite($socket, pack('N', 0));
            $answer = trim((string) stream_get_contents($socket), "\0 \n");
        } finally {
            fclose($socket);
        }

        if (str_contains($answer, 'FOUND')) {
            throw new InvalidArgumentException('O arquivo foi recusado pela verificação de segurança (possível vírus).');
        }

        if (! str_ends_with($answer, 'OK')) {
            self::unavailable($failClosed, "Resposta inesperada do ClamAV: {$answer}");
        }
    }

    private static function unavailable(bool $failClosed, string $reason): void
    {
        if ($failClosed) {
            throw new InvalidArgumentException('Não foi possível verificar o arquivo agora. Tente novamente em instantes.');
        }

        logger()->warning($reason);
    }
}
