<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

#[Signature('webpush:keys')]
#[Description('Gera o par de chaves VAPID dos avisos push (copie para o .env; guarde a privada como segredo)')]
class GenerateWebPushKeys extends Command
{
    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->warn('Troque as chaves só se for preciso: quem já pediu aviso terá de pedir de novo.');

        return self::SUCCESS;
    }
}
