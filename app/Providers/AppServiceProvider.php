<?php

namespace App\Providers;

use App\Modules\Matches\Events\GameUpdated;
use App\Modules\Matches\Models\Game;
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

        // Placar ao vivo: qualquer mudança de placar, situação ou horários do jogo é transmitida.
        Game::updated(function (Game $game): void {
            if ($game->wasChanged(['home_score', 'away_score', 'home_penalties', 'away_penalties', 'status', 'started_at', 'finished_at'])) {
                event(GameUpdated::for($game));
            }
        });
    }
}
