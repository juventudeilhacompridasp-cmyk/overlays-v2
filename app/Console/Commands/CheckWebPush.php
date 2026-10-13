<?php

namespace App\Console\Commands;

use App\Modules\Portal\Models\PushSubscription;
use App\Modules\Tenancy\Enums\Feature;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Minishlink\WebPush\ContentEncoding;
use Minishlink\WebPush\VAPID;
use Throwable;

#[Signature('push:check')]
#[Description('Confere a configuração dos avisos push (chaves VAPID, HTTPS, recurso e pedidos)')]
class CheckWebPush extends Command
{
    private bool $failed = false;

    public function handle(): int
    {
        $public = (string) config('webpush.public_key');
        $private = (string) config('webpush.private_key');
        $subject = (string) config('webpush.subject');

        if ($public === '' || $private === '') {
            $this->bad('VAPID_PUBLIC_KEY e VAPID_PRIVATE_KEY vazias: o recurso fica desligado. Gere o par com `php artisan webpush:keys`.');

            return self::FAILURE;
        }

        $this->keys($public, $private);
        $this->subject($subject);
        $this->site();
        $this->usage();

        return $this->failed ? self::FAILURE : self::SUCCESS;
    }

    private function keys(string $public, string $private): void
    {
        // base64 url-safe sem preenchimento (como o createVapidKeys entrega): completa o "=" antes de decodificar.
        $decode = fn (string $value): string|false => base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true);
        $decodedPublic = $decode($public);
        $decodedPrivate = $decode($private);

        if ($decodedPublic === false || strlen($decodedPublic) !== 65 || $decodedPublic[0] !== "\x04") {
            $this->bad('VAPID_PUBLIC_KEY não é uma chave pública P-256 (65 bytes, base64 url-safe).');

            return;
        }

        if ($decodedPrivate === false || strlen($decodedPrivate) !== 32) {
            $this->bad('VAPID_PRIVATE_KEY não é uma chave privada P-256 (32 bytes, base64 url-safe).');

            return;
        }

        try {
            // A biblioteca assina com as chaves já decodificadas (é o que `validate` devolve).
            $vapid = VAPID::validate(['subject' => (string) config('webpush.subject'), 'publicKey' => $public, 'privateKey' => $private]);
            VAPID::getVapidHeaders('https://fcm.googleapis.com', $vapid['subject'], $vapid['publicKey'], $vapid['privateKey'], ContentEncoding::aes128gcm);
            $this->ok('chaves VAPID válidas (assinam o cabeçalho de autorização)');
        } catch (Throwable $exception) {
            $this->bad('não consegui assinar com as chaves VAPID: '.$exception->getMessage());
        }
    }

    private function subject(string $subject): void
    {
        if (! str_starts_with($subject, 'mailto:') && ! str_starts_with($subject, 'https://')) {
            $this->bad("VAPID_SUBJECT={$subject}: use mailto:contato@seudominio ou uma URL https (os serviços de push exigem).");

            return;
        }

        $subject === 'mailto:contato@example.com'
            ? $this->note('VAPID_SUBJECT ainda é o e-mail de exemplo: troque por um contato real (o serviço de push usa para falar com você).')
            : $this->ok("VAPID_SUBJECT={$subject}");
    }

    private function site(): void
    {
        $site = (string) config('app.url');
        $local = (bool) preg_match('#^http://(localhost|127\.0\.0\.1)(:\d+)?#', $site);

        if (str_starts_with($site, 'https://') || $local) {
            $this->ok("APP_URL={$site} (contexto seguro: o navegador aceita service worker e push)");

            return;
        }

        $this->bad("APP_URL={$site}: push e PWA só funcionam em HTTPS (ou localhost). Ajuste o endereço e o certificado.");
    }

    private function usage(): void
    {
        $enabled = Organization::query()->get()->filter(fn (Organization $organization) => $organization->hasFeature(Feature::Push))->count();
        $this->line("  Organizações com o recurso push: {$enabled}. Pedidos de aviso guardados: ".PushSubscription::query()->withoutGlobalScopes()->count().'.');
        $this->line('  Para conferir de verdade: abra um jogo, toque em "Avisar este jogo", aceite a permissão e mude o placar com a aba fechada.');
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
