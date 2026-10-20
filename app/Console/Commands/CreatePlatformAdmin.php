<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Support\OrganizationResolver;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

#[Signature('platform:create-admin {email : E-mail do administrador} {--name= : Nome (padrão: a parte antes do @)} {--password= : Senha (evite: fica no histórico do shell; sem a opção ela é pedida sem eco)} {--platform : Também dá acesso à área /plataforma}')]
#[Description('Cria (ou promove) o administrador da organização padrão. É o jeito seguro de começar uma instalação pública (ALLOW_FIRST_USER_SETUP=false)')]
class CreatePlatformAdmin extends Command
{
    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $password = (string) ($this->option('password') ?: $this->secret('Senha (mínimo 12 caracteres)'));
            $name = trim((string) ($this->option('name') ?: strstr($email, '@', true)));

            $validator = Validator::make(
                ['email' => $email, 'name' => $name, 'password' => $password],
                ['email' => ['required', 'email', 'max:255'], 'name' => ['required', 'string', 'max:255'], 'password' => ['required', Password::min(12)->letters()->numbers()]],
            );

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $message) {
                    $this->error($message);
                }

                return self::FAILURE;
            }

            $user = User::query()->create(['name' => $name, 'email' => $email, 'password' => Hash::make($password)]);
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        CurrentOrganization::using(OrganizationResolver::defaultOrganization(), function () use ($user): void {
            Membership::query()->updateOrCreate(['user_id' => $user->getKey()], ['role' => Role::Admin]);
        });

        if ($this->option('platform')) {
            $user->forceFill(['is_platform_admin' => true])->save();
        }

        $this->info("{$user->email} agora administra a organização padrão".($this->option('platform') ? ' e a plataforma (/plataforma).' : '.'));

        return self::SUCCESS;
    }
}
