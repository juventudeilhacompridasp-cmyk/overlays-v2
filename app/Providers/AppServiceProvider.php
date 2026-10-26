<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\Broadcast\Listeners\AutomateOverlayScreens;
use App\Modules\Broadcast\Listeners\ShowLowerThird;
use App\Modules\Broadcast\Listeners\SyncOverlays;
use App\Modules\Market\Listeners\MarkHiredProfiles;
use App\Modules\Matches\Events\GameEventRecorded;
use App\Modules\Matches\Events\GameUpdated;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Portal\Push\GamePushNotifier;
use App\Modules\Portal\Push\PushSender;
use App\Modules\Portal\Push\WebPushSender;
use App\Modules\Portal\Support\PublicTheme;
use App\Modules\Registration\Events\SupplementApproved;
use App\Modules\Teams\Support\GameNotices;
use App\Modules\Tenancy\Actions\ManageMembers;
use App\Modules\Tenancy\Enums\Ability;
use App\Modules\Tenancy\Enums\Permission;
use App\Modules\Tenancy\Support\ActivityRecorder;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PushSender::class, WebPushSender::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Permissões do painel: cada ability vale conforme o papel do usuário na organização atual.
        foreach (Ability::cases() as $ability) {
            Gate::define($ability->value, fn (User $user) => $user->hasPermission(Permission::forAbility($ability)));
        }

        // Instalação nova: o primeiro cadastro vira administrador (depois disso, usuários só são criados por um administrador).
        Event::listen(Registered::class, function (Registered $event): void {
            if ($event->user instanceof User) {
                app(ManageMembers::class)->claimFirstAdmin($event->user);
            }
        });

        // E-mails de conta (recuperar senha, confirmar e-mail) no mesmo visual dos demais e em português.
        // Não vão para a fila (sem `ShouldQueue`), então a organização atual ainda está definida aqui.
        ResetPassword::toMailUsing(function (object $notifiable, string $url): MailMessage {
            $theme = PublicTheme::current();
            $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

            return (new MailMessage)
                ->subject('Recuperação de senha')
                ->view('mail.account-action', [
                    'organization' => $theme['name'],
                    'color' => $theme['primary'],
                    'name' => $notifiable->name ?? null,
                    'headline' => 'Recebemos um pedido para criar uma nova senha para a sua conta.',
                    'label' => 'Criar nova senha',
                    'url' => $url,
                    'footer' => "Este link vale por {$minutes} minutos. Se você não pediu a recuperação, ignore esta mensagem: a sua senha continua a mesma.",
                ]);
        });

        VerifyEmail::toMailUsing(function (object $notifiable, string $url): MailMessage {
            $theme = PublicTheme::current();

            return (new MailMessage)
                ->subject('Confirme o seu e-mail')
                ->view('mail.account-action', [
                    'organization' => $theme['name'],
                    'color' => $theme['primary'],
                    'name' => $notifiable->name ?? null,
                    'headline' => 'Confirme o seu endereço de e-mail para concluir o acesso à sua conta.',
                    'label' => 'Confirmar e-mail',
                    'url' => $url,
                    'footer' => 'Se você não criou uma conta, ignore esta mensagem.',
                ]);
        });

        // Mercado de jogadores: reforço aprovado tira da busca quem foi contratado.
        Event::listen(SupplementApproved::class, MarkHiredProfiles::class);

        // Transmissão (overlays do OBS): reage ao placar ao vivo e aos lances da súmula.
        Event::listen(GameUpdated::class, SyncOverlays::class);
        Event::listen(GameUpdated::class, AutomateOverlayScreens::class);
        Event::listen(GameEventRecorded::class, ShowLowerThird::class);

        // Auditoria: mudanças de jogo e lances feitos por quem está logado (antes/depois) vão ao registro de atividades.
        Game::updated(ActivityRecorder::gameChanged(...));
        GameEvent::created(ActivityRecorder::eventRecorded(...));

        // Placar ao vivo: qualquer mudança de placar, situação ou horários do jogo é transmitida.
        Game::updated(function (Game $game): void {
            if ($game->wasChanged(['home_score', 'away_score', 'home_penalties', 'away_penalties', 'sets', 'status', 'started_at', 'finished_at'])) {
                event(GameUpdated::for($game));
                GamePushNotifier::afterUpdate($game);
            }

            // Avisos por e-mail ao gestor: horário/local/situação alterados e suspensões de um jogo encerrado.
            if ($game->wasChanged(['scheduled_at', 'venue_id', 'status'])) {
                GameNotices::afterUpdate($game);
            }
        });
    }
}
