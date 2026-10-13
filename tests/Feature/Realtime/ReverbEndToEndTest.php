<?php

use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;

/*
| Verificação ponta a ponta do tempo real: sobe um servidor Reverb DE VERDADE, liga um cliente WebSocket (o mesmo
| pusher-js do front, em Node) a um canal público e confere que o evento sai do Laravel, passa pelo Reverb e chega.
| Só roda no job "Tempo real" do CI (RUN_E2E=1): precisa de Node e do `npm ci`.
*/

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
});

function e2eFreePort(): int
{
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) parse_url((string) stream_socket_get_name($socket, false), PHP_URL_PORT);
    fclose($socket);

    return $port;
}

function e2eWaitForPort(int $port, float $seconds = 20): void
{
    $until = microtime(true) + $seconds;

    while (microtime(true) < $until) {
        $socket = @fsockopen('127.0.0.1', $port, $code, $message, 0.2);

        if ($socket !== false) {
            fclose($socket);

            return;
        }

        usleep(100_000);
    }

    throw new RuntimeException("Nada escutando em 127.0.0.1:{$port} depois de {$seconds}s.");
}

function e2eWaitForOutput(Process $process, string $needle, float $seconds = 15): void
{
    $until = microtime(true) + $seconds;

    while (microtime(true) < $until) {
        if (str_contains($process->getOutput(), $needle)) {
            return;
        }

        if (! $process->isRunning()) {
            break;
        }

        usleep(100_000);
    }

    throw new RuntimeException("Não apareceu \"{$needle}\" na saída do processo. Saída: {$process->getOutput()} {$process->getErrorOutput()}");
}

/** Aponta o envio de eventos do Laravel para o Reverb que subiu na porta informada. */
function e2eUseReverb(int $port): void
{
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.options.host' => '127.0.0.1',
        'broadcasting.connections.reverb.options.port' => $port,
        'broadcasting.connections.reverb.options.scheme' => 'http',
        'broadcasting.connections.reverb.options.useTLS' => false,
        'platform.realtime.port' => $port,
    ]);

    app(BroadcastManager::class)->purge('reverb');
}

/** @return array{server: Process, port: int, key: string} */
function e2eStartReverb(): array
{
    $key = (string) config('broadcasting.connections.reverb.key');
    expect($key)->not->toBe('', 'REVERB_APP_KEY vazio: copie o .env.example para .env antes de rodar o e2e.');
    expect(is_dir(base_path('node_modules/pusher-js')))->toBeTrue('Rode `npm ci` antes do e2e (precisa do pusher-js).');

    $port = e2eFreePort();
    $server = new Process([PHP_BINARY, 'artisan', 'reverb:start', '--host=127.0.0.1', "--port={$port}"], base_path(), null, null, 120);
    $server->start();

    try {
        e2eWaitForPort($port);
    } catch (Throwable $exception) {
        throw new RuntimeException($exception->getMessage().' Saída do Reverb: '.$server->getOutput().$server->getErrorOutput());
    }

    return ['server' => $server, 'port' => $port, 'key' => $key];
}

function e2eSubscribe(int $port, string $key, string $channel, string $event): Process
{
    $client = new Process(['node', base_path('tests/e2e/subscribe.mjs'), (string) $port, $key, $channel, $event, '20000'], base_path(), null, null, 40);
    $client->start();
    e2eWaitForOutput($client, 'SUBSCRIBED');

    return $client;
}

describe('Reverb', function () {
    it('delivers a score change to a real WebSocket client on the public game channel', function () {
        ['server' => $server, 'port' => $port, 'key' => $key] = e2eStartReverb();

        try {
            ['game' => $game] = sheetGame();
            $client = e2eSubscribe($port, $key, "games.{$game->id}", 'game.updated');

            e2eUseReverb($port);
            // O caminho real do app: o placar muda no modelo, o gancho dispara o GameUpdated e ele sai pelo Reverb.
            $game->update(['home_score' => 2, 'away_score' => 1, 'status' => GameStatus::InProgress]);

            $client->wait();

            expect($client->getExitCode())->toBe(0, $client->getOutput().$client->getErrorOutput());

            preg_match('/^EVENT (.+)$/m', $client->getOutput(), $matches);
            $payload = json_decode($matches[1] ?? '{}', true);

            expect($payload['snapshot']['id'] ?? null)->toBe($game->id)
                ->and($payload['snapshot']['home_score'] ?? null)->toBe(2)
                ->and($payload['snapshot']['away_score'] ?? null)->toBe(1)
                ->and($payload['snapshot']['status'] ?? null)->toBe('in_progress');
        } finally {
            $server->stop();
        }
    })->skip(fn () => ! env('RUN_E2E'), 'roda só no job de tempo real do CI (RUN_E2E=1)');

    it('passes the realtime:check diagnostic against a live server and the ping reaches a client', function () {
        ['server' => $server, 'port' => $port, 'key' => $key] = e2eStartReverb();

        try {
            $client = e2eSubscribe($port, $key, 'realtime.check', 'realtime.ping');

            e2eUseReverb($port);
            config(['broadcasting.connections.reverb.options.host' => '127.0.0.1']);

            $exit = Artisan::call('realtime:check');
            $output = Artisan::output();

            expect($exit)->toBe(0, $output)
                ->and($output)->toContain('evento de teste aceito pelo Reverb');

            $client->wait();

            expect($client->getExitCode())->toBe(0, $client->getOutput())
                ->and($client->getOutput())->toContain('EVENT {"token":"');
        } finally {
            $server->stop();
        }
    })->skip(fn () => ! env('RUN_E2E'), 'roda só no job de tempo real do CI (RUN_E2E=1)');
});
