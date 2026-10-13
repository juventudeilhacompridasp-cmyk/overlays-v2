<?php

use App\Modules\Portal\Models\PushSubscription;
use App\Modules\Portal\Push\WebPushSender;
use Minishlink\WebPush\VAPID;
use Symfony\Component\Process\Process;

/*
| Verificação ponta a ponta do envio Web Push: o remetente DE VERDADE (minishlink + VAPID) manda para um "serviço de
| push" local escrito em Node, que confere de forma independente a assinatura VAPID (ES256) e decifra o corpo
| (RFC 8291, aes128gcm) com as chaves do "navegador". Cobre o que dá para provar sem um navegador e sem o FCM/Apple.
| Só roda no job "Tempo real" do CI (RUN_E2E=1).
*/

function e2ePushB64(string $binary): string
{
    return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
}

it('sends a push that an independent receiver verifies (VAPID signature) and decrypts (aes128gcm)', function () {
    $vapid = VAPID::createVapidKeys();
    config(['webpush.public_key' => $vapid['publicKey'], 'webpush.private_key' => $vapid['privateKey'], 'webpush.subject' => 'mailto:push@exemplo.com']);

    // Chaves do "navegador" (P-256) e segredo de autenticação, como na PushSubscription real.
    $pair = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $ec = openssl_pkey_get_details($pair)['ec'];
    [$d, $x, $y] = array_map(fn (string $part) => str_pad($part, 32, "\0", STR_PAD_LEFT), [$ec['d'], $ec['x'], $ec['y']]);
    $auth = random_bytes(16);

    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) parse_url((string) stream_socket_get_name($socket, false), PHP_URL_PORT);
    fclose($socket);

    $receiver = new Process(['node', base_path('tests/e2e/push-service.mjs'), (string) $port, $vapid['publicKey'], e2ePushB64($d), e2ePushB64($x), e2ePushB64($y), e2ePushB64($auth), '20000'], base_path(), null, null, 40);
    $receiver->start();

    $until = microtime(true) + 10;
    while (! str_contains($receiver->getOutput(), 'LISTENING') && microtime(true) < $until) {
        usleep(100_000);
    }
    expect($receiver->getOutput())->toContain('LISTENING', $receiver->getErrorOutput());

    $subscription = new PushSubscription;
    $subscription->forceFill(['endpoint' => "http://127.0.0.1:{$port}/push/teste", 'p256dh' => e2ePushB64("\x04".$x.$y), 'auth' => e2ePushB64($auth)]);

    $payload = ['title' => 'Gol! Ilha FC 1 x 0 Vila', 'body' => 'Toque para acompanhar o jogo', 'url' => 'https://exemplo.com/jogos/01J'];

    $expired = app(WebPushSender::class)->send([$subscription], $payload);

    $receiver->wait();

    preg_match('/^RESULT (.+)$/m', $receiver->getOutput(), $matches);
    $result = json_decode($matches[1] ?? '{}', true);

    expect($result['ok'] ?? null)->toBeTrue($receiver->getOutput().$receiver->getErrorOutput())
        ->and($result['payload'] ?? null)->toBe($payload)
        ->and($expired)->toBe([]);
})->skip(fn () => ! env('RUN_E2E'), 'roda só no job de tempo real do CI (RUN_E2E=1)');
