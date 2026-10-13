<?php

namespace App\Console\Commands;

use App\Modules\Broadcast\Events\RealtimePing;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

#[Signature('realtime:check')]
#[Description('Confere a configuração do tempo real (Reverb) e manda um evento de teste ao servidor')]
class CheckRealtime extends Command
{
    private bool $failed = false;

    public function handle(): int
    {
        $this->line('Servidor (o que o Laravel enxerga):');

        $driver = (string) config('broadcasting.default');
        $driver === 'reverb'
            ? $this->ok('BROADCAST_CONNECTION=reverb')
            : $this->bad("BROADCAST_CONNECTION={$driver}: os eventos não saem pelo Reverb (use reverb)");

        /** @var array<string, mixed> $reverb */
        $reverb = (array) config('broadcasting.connections.reverb');
        /** @var array<string, mixed> $options */
        $options = (array) ($reverb['options'] ?? []);

        $missing = array_keys(array_filter(['REVERB_APP_ID' => $reverb['app_id'] ?? null, 'REVERB_APP_KEY' => $reverb['key'] ?? null, 'REVERB_APP_SECRET' => $reverb['secret'] ?? null, 'REVERB_HOST' => $options['host'] ?? null], fn ($value) => blank($value)));

        $missing === []
            ? $this->ok('credenciais e host do Reverb preenchidos')
            : $this->bad('faltam no .env: '.implode(', ', $missing));

        if ($missing === [] && $driver === 'reverb') {
            $this->reach((string) $options['host'], (int) ($options['port'] ?? 443));
            $this->ping();
        }

        $this->line('');
        $this->line('Navegador (o que o site entrega às páginas e ao OBS):');
        $this->publicSide();

        $this->line('');
        $this->line('Lembrete: com BROADCAST_CONNECTION=reverb e fila redis, o Horizon precisa estar rodando para os eventos saírem.');

        return $this->failed ? self::FAILURE : self::SUCCESS;
    }

    private function reach(string $host, int $port): void
    {
        $socket = @fsockopen($host, $port, $code, $message, 3);

        if ($socket === false) {
            $this->bad("não consegui conectar em {$host}:{$port} ({$message}). O servidor Reverb está de pé e acessível a partir daqui?");

            return;
        }

        fclose($socket);
        $this->ok("{$host}:{$port} aceita conexões");
    }

    private function ping(): void
    {
        try {
            broadcast(new RealtimePing(Str::lower(Str::random(8))));
            $this->ok('evento de teste aceito pelo Reverb (canal público realtime.check)');
        } catch (Throwable $exception) {
            $this->bad('o Reverb recusou o evento de teste: '.$exception->getMessage());
        }
    }

    private function publicSide(): void
    {
        /** @var array{key: string|null, host: string|null, port: int, scheme: string} $public */
        $public = (array) config('platform.realtime');
        $site = (string) config('app.url');

        filled($public['key']) ? $this->ok('chave pública do Reverb (REVERB_APP_KEY) disponível às páginas') : $this->bad('REVERB_APP_KEY vazia: as páginas ficam só no polling de contingência');

        if (blank($public['host'])) {
            $this->note('REVERB_PUBLIC_HOST vazio: o navegador tentará o mesmo host do site na porta '.$public['port'].'.');
        } else {
            $this->ok("o navegador conecta em {$public['scheme']}://{$public['host']}:{$public['port']}");
        }

        if (str_starts_with($site, 'https://') && $public['scheme'] !== 'https') {
            $this->bad('o site é HTTPS mas REVERB_PUBLIC_SCHEME não é https: o navegador bloqueia o WebSocket (conteúdo misto). Use REVERB_PUBLIC_SCHEME=https e a porta do proxy.');
        }

        $this->line('  Para conferir de verdade: abra uma página de jogo ao vivo, F12 > Rede > WS e veja a conexão "101 Switching Protocols".');
    }

    private function ok(string $message): void
    {
        $this->line("  <info>✓</info> {$message}");
    }

    private function note(string $message): void
    {
        $this->line("  <comment>!</comment> {$message}");
    }

    private function bad(string $message): void
    {
        $this->failed = true;
        $this->line("  <error>✗</error> {$message}");
    }
}
