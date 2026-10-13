<?php

namespace App\Modules\Portal\Jobs;

use App\Modules\Matches\Models\Game;
use App\Modules\Portal\Models\PushSubscription;
use App\Modules\Portal\Push\PushSender;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Avisa quem acompanha o jogo (ou uma das equipes): começou, gol/set, terminou. Só dados públicos (equipes e placar). */
class SendGamePush implements ShouldQueue
{
    use Queueable;

    public const STARTED = 'started';

    public const SCORE = 'score';

    public const FINISHED = 'finished';

    public function __construct(public string $organizationId, public string $gameId, public string $kind, public string $url) {}

    public function handle(PushSender $sender): void
    {
        $organization = Organization::query()->find($this->organizationId);

        if ($organization === null) {
            return;
        }

        CurrentOrganization::using($organization, function () use ($sender): void {
            $game = Game::query()->with(['homeEntry.team', 'awayEntry.team', 'division.season.competition'])->find($this->gameId);

            if ($game === null || $game->homeEntry === null || $game->awayEntry === null || ! $game->division->season->isPublished()) {
                return;
            }

            $teams = [$game->homeEntry->team_id, $game->awayEntry->team_id];

            $subscriptions = PushSubscription::query()
                ->where(fn ($query) => $query
                    ->where(fn ($q) => $q->where('target_type', PushSubscription::GAME)->where('target_id', $game->getKey()))
                    ->orWhere(fn ($q) => $q->where('target_type', PushSubscription::TEAM)->whereIn('target_id', $teams)))
                ->get()
                ->unique('endpoint_hash'); // quem segue o jogo e a equipe recebe um aviso só

            if ($subscriptions->isEmpty()) {
                return;
            }

            $home = $game->homeEntry->team->name;
            $away = $game->awayEntry->team->name;
            $score = "{$home} {$game->home_score} x {$game->away_score} {$away}";
            $unit = $game->sport()->profile()->scoreModel;

            [$title, $body] = match ($this->kind) {
                self::STARTED => ['Começou!', "{$home} x {$away}"],
                self::FINISHED => ['Fim de jogo', $score],
                default => [$unit === 'sets' ? 'Set encerrado' : 'GOL!', $score],
            };

            $expired = $sender->send($subscriptions, ['title' => $title, 'body' => $body, 'url' => $this->url, 'tag' => "game-{$game->getKey()}"]);

            if ($expired !== []) {
                PushSubscription::query()->whereIn('id', $expired)->delete();
            }
        });
    }
}
