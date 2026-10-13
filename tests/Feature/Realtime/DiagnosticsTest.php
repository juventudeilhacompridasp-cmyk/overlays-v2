<?php

use Minishlink\WebPush\VAPID;

describe('realtime:check', function () {
    it('fails when events do not go through Reverb', function () {
        config(['broadcasting.default' => 'null']);

        $this->artisan('realtime:check')
            ->expectsOutputToContain('BROADCAST_CONNECTION=null')
            ->assertExitCode(1);
    });

    it('lists the missing credentials', function () {
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => null, 'broadcasting.connections.reverb.options.host' => null]);

        $this->artisan('realtime:check')
            ->expectsOutputToContain('REVERB_APP_KEY')
            ->expectsOutputToContain('REVERB_HOST')
            ->assertExitCode(1);
    });

    it('reports a server that does not answer', function () {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'k', 'broadcasting.connections.reverb.secret' => 's', 'broadcasting.connections.reverb.app_id' => '1',
            'broadcasting.connections.reverb.options.host' => '127.0.0.1', 'broadcasting.connections.reverb.options.port' => 1,
        ]);

        $this->artisan('realtime:check')
            ->expectsOutputToContain('não consegui conectar em 127.0.0.1:1')
            ->assertExitCode(1);
    });

    it('warns about the mixed content of an https site with an http WebSocket', function () {
        config(['broadcasting.default' => 'null', 'app.url' => 'https://exemplo.com', 'platform.realtime.scheme' => 'http', 'platform.realtime.key' => 'k', 'platform.realtime.host' => 'exemplo.com']);

        $this->artisan('realtime:check')
            ->expectsOutputToContain('conteúdo misto')
            ->assertExitCode(1);
    });
});

describe('push:check', function () {
    it('fails without VAPID keys', function () {
        config(['webpush.public_key' => null, 'webpush.private_key' => null]);

        $this->artisan('push:check')->expectsOutputToContain('webpush:keys')->assertExitCode(1);
    });

    it('rejects keys that are not P-256 keys', function () {
        config(['webpush.public_key' => 'curta', 'webpush.private_key' => 'curta']);

        $this->artisan('push:check')->expectsOutputToContain('não é uma chave pública P-256')->assertExitCode(1);
    });

    it('accepts a valid pair on a secure site', function () {
        $keys = VAPID::createVapidKeys();
        config(['webpush.public_key' => $keys['publicKey'], 'webpush.private_key' => $keys['privateKey'], 'webpush.subject' => 'mailto:push@exemplo.com', 'app.url' => 'https://exemplo.com']);

        $this->artisan('push:check')->expectsOutputToContain('chaves VAPID válidas')->assertExitCode(0);
    });

    it('fails on a site without https and on a subject that the push services reject', function () {
        $keys = VAPID::createVapidKeys();
        config(['webpush.public_key' => $keys['publicKey'], 'webpush.private_key' => $keys['privateKey'], 'webpush.subject' => 'contato@exemplo.com', 'app.url' => 'http://exemplo.com']);

        $this->artisan('push:check')
            ->expectsOutputToContain('VAPID_SUBJECT=contato@exemplo.com')
            ->expectsOutputToContain('só funcionam em HTTPS')
            ->assertExitCode(1);
    });
});
