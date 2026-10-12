<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('platform:make-admin {email : E-mail de um usuário já cadastrado} {--revoke : Remove o acesso à área da plataforma}')]
#[Description('Dá (ou tira) o acesso à área da plataforma (/plataforma) a um usuário')]
class MakePlatformAdmin extends Command
{
    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('Nenhum usuário com esse e-mail.');

            return self::FAILURE;
        }

        $user->forceFill(['is_platform_admin' => ! $this->option('revoke')])->save();
        $this->info($this->option('revoke') ? "{$user->email} não administra mais a plataforma." : "{$user->email} agora administra a plataforma (/plataforma).");

        return self::SUCCESS;
    }
}
