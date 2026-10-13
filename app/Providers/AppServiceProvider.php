<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\Broadcast\Listeners\ShowLowerThird;
use App\Modules\Broadcast\Listeners\SyncOverlays;
use App\Modules\Matches\Events\GameEventRecorded;
use App\Modules\Matches\Events\GameUpdated;
use App\Modules\Matches\Models\Game;
use App\Modules\Tenancy\Actions\ManageMembers;
use App\Modules\Tenancy\Enums\Ability;
use Illuminate\Auth\Events\Registered;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Permissões do painel: cada ability vale conforme o papel do usuário na organização atual.
        foreach (Ability::cases() as $ability) {
            Gate::define($ability->value, fn (User $user) => $user->role()?->can($ability) ?? false);
        }

        // Instalação nova: o primeiro cadastro vira administrador (depois disso, usuários só são criados por um administrador).
        Event::listen(Registered::class, function (Registered $event): void {
            if ($event->user instanceof User) {
                app(ManageMembers::class)->claimFirstAdmin($event->user);
            }
        });

        // Transmissão (overlays do OBS): reage ao placar ao vivo e aos lances da súmula.
        Event::listen(GameUpdated::class, SyncOverlays::class);
        Event::listen(GameEventRecorded::class, ShowLowerThird::class);

        // Placar ao vivo: qualquer mudança de placar, situação ou horários do jogo é transmitida.
        Game::updated(function (Game $game): void {
            if ($game->wasChanged(['home_score', 'away_score', 'home_penalties', 'away_penalties', 'sets', 'status', 'started_at', 'finished_at'])) {
                event(GameUpdated::for($game));
            }
        });
    }
}
