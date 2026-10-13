<?php

namespace App\Modules\Teams\Actions;

use App\Models\User;
use App\Modules\Teams\Mail\TeamManagerInvitation;
use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Models\TeamManager;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Membership;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Convida um gestor para uma equipe (ADR 0003). E-mail novo: cria o usuário (e-mail verificado, senha aleatória) e
 * envia o link para ele definir a própria senha — ninguém repassa senha. E-mail já cadastrado: só ganha o vínculo.
 * Quem já tem papel na equipe interna da organização (administrador, organizador...) não vira gestor ali.
 * O link do e-mail é montado AQUI, na requisição (na fila o host seria o do APP_URL, não o da organização).
 */
final class InviteTeamManager
{
    public function handle(Team $team, string $name, string $email, ?User $invitedBy = null): TeamManager
    {
        $email = mb_strtolower(trim($email));

        $created = false;

        $link = DB::transaction(function () use ($team, $name, $email, $invitedBy, &$created): TeamManager {
            $user = User::query()->where('email', $email)->first();

            if ($user === null) {
                $user = User::query()->create(['name' => trim($name), 'email' => $email, 'password' => Hash::make(Str::random(40))]);
                $user->forceFill(['email_verified_at' => now()])->save();
                $created = true;
            }

            $membership = Membership::query()->where('user_id', $user->getKey())->first();

            if ($membership !== null && $membership->role !== Role::TeamManager) {
                throw new InvalidArgumentException('Este e-mail já tem acesso ao painel da organização como '.mb_strtolower($membership->role->label()).'. Use outro e-mail para o gestor.');
            }

            if ($membership === null) {
                Membership::query()->create(['user_id' => $user->getKey(), 'role' => Role::TeamManager]);
            }

            $user->forgetRole();

            return TeamManager::query()->firstOrCreate(
                ['user_id' => $user->getKey(), 'team_id' => $team->getKey()],
                ['invited_by' => $invitedBy?->getKey()],
            );
        });

        $this->notify($link->load('user', 'team'), $created);

        return $link;
    }

    /** Novo link de definição de senha (convite perdido ou vencido). */
    public function resend(TeamManager $link): void
    {
        $this->notify($link->load('user', 'team'), true);
    }

    private function notify(TeamManager $link, bool $newAccount): void
    {
        $user = $link->user;
        $url = $newAccount
            ? route('password.reset', ['token' => Password::broker()->createToken($user), 'email' => $user->email])
            : route('manager.dashboard');

        Mail::to($user->email)->queue(new TeamManagerInvitation($user->name, $link->team->name, $url, $newAccount));
    }
}
