<?php

namespace App\Modules\Matches\Actions;

use App\Modules\Competitions\Enums\StageType;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Events\GameUpdated;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Rules\Shootout\ShootoutState;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Lança UMA cobrança da disputa de pênaltis (convertida ou perdida), na ordem em que acontece. Os times se alternam a
 * partir de quem cobrou primeiro (o primeiro lançamento abre a disputa para qualquer lado) e a disputa fecha sozinha quando
 * há vencedor (`ShootoutState`). A cobrança é anônima (sem cobrador). Desfazer = `UndoShootoutKick`.
 */
final class RecordShootoutKick
{
    public function __construct(private readonly ProjectGameScore $projectScore = new ProjectGameScore) {}

    public function handle(Game $game, string $side, bool $scored, ?int $recordedBy = null): Game
    {
        if (! in_array($side, ['home', 'away'], true)) {
            throw new InvalidArgumentException('Escolha a equipe que cobrou.');
        }

        self::ensureOpen($game);

        $entryId = $side === 'home' ? $game->home_entry_id : $game->away_entry_id;

        $game = DB::transaction(function () use ($game, $side, $scored, $entryId, $recordedBy): Game {
            $events = $game->events()->effective()->where('type', GameEventType::PenaltyKick)->orderBy('id')->get();

            if ($events->contains(fn (GameEvent $event) => ! isset($event->payload['kick']))) {
                throw new InvalidArgumentException('A disputa foi lançada pelo total. Apague o total (salve em branco) antes de lançar cobrança a cobrança.');
            }

            $kicks = $events->map(fn (GameEvent $event) => ['side' => $event->entry_id === $game->home_entry_id ? 'home' : 'away', 'scored' => ($event->payload['scored'] ?? false) === true])->all();
            $state = ShootoutState::from($kicks);

            if ($state['decided']) {
                throw new InvalidArgumentException('A disputa já está decidida. Desfaça a última cobrança para corrigir.');
            }

            if ($state['next'] !== null && $state['next'] !== $side) {
                throw new InvalidArgumentException('Não é a vez desta equipe: as cobranças se alternam.');
            }

            if (count($state[$side]) >= ShootoutState::MAX_PER_SIDE) {
                throw new InvalidArgumentException('Limite de '.ShootoutState::MAX_PER_SIDE.' cobranças por equipe atingido.');
            }

            GameEvent::query()->create([
                'organization_id' => $game->organization_id,
                'game_id' => $game->getKey(),
                'type' => GameEventType::PenaltyKick,
                'period' => 1,
                'entry_id' => $entryId,
                'payload' => ['shootout' => true, 'scored' => $scored, 'kick' => count($kicks) + 1],
                'recorded_by' => $recordedBy,
            ]);

            return $this->projectScore->handle($game);
        });

        // Placar de pênaltis mudou: transmite o retrato completo (a disputa vai no snapshot público).
        event(GameUpdated::for($game));

        return $game;
    }

    /** Só jogo de mata-mata, com os dois confrontantes e ainda aberto. */
    public static function ensureOpen(Game $game): void
    {
        if ($game->stage->type !== StageType::Knockout) {
            throw new InvalidArgumentException('Só jogos de mata-mata têm disputa de pênaltis.');
        }

        if ($game->home_entry_id === null || $game->away_entry_id === null) {
            throw new InvalidArgumentException('O jogo ainda não tem os dois confrontantes definidos.');
        }

        if ($game->status->isDecided()) {
            throw new InvalidArgumentException('O jogo já foi encerrado. Reabra para alterar os pênaltis.');
        }
    }
}
