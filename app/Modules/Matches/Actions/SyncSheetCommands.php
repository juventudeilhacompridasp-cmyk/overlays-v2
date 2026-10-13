<?php

namespace App\Modules\Matches\Actions;

use App\Models\User;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Matches\Models\SheetCommand;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Aplica, em ordem, os lançamentos que o aparelho do mesário guardou offline (ADR da fila offline): cada item passa pelas
 * MESMAS ações da súmula (mesma validação do regulamento), com a hora em que o lance aconteceu (`at`, nunca no futuro).
 * - cada item tem uma chave do aparelho: chave já aplicada = `duplicate` (reenvio seguro, inclusive de duas abas);
 * - o primeiro item recusado interrompe o lote (os seguintes dependem dele): vem `rejected` com o motivo e os demais `skipped`;
 * - lotes do mesmo jogo são serializados por um trinco, para duas abas não aplicarem a mesma chave ao mesmo tempo.
 */
final class SyncSheetCommands
{
    public const KINDS = ['event', 'point', 'period', 'clock', 'void', 'finish'];

    public function __construct(
        private readonly RecordGameEvent $record = new RecordGameEvent,
        private readonly ChangeGamePeriod $period = new ChangeGamePeriod,
        private readonly VoidGameEvent $void = new VoidGameEvent,
        private readonly FinishGame $finish = new FinishGame,
    ) {}

    /**
     * @param  list<array{key: string, kind: string, at: int, payload: array<string, mixed>}>  $items
     * @return list<array{key: string, status: string, message: string|null}>
     *
     * @throws \Illuminate\Contracts\Cache\LockTimeoutException
     */
    public function handle(Game $game, array $items, User $by): array
    {
        return Cache::lock("sheet-sync:{$game->getKey()}", 30)->block(10, function () use ($game, $items, $by): array {
            $results = [];
            $blocked = false;

            foreach ($items as $item) {
                if ($blocked) {
                    $results[] = ['key' => $item['key'], 'status' => 'skipped', 'message' => null];

                    continue;
                }

                $already = SheetCommand::query()->where('game_id', $game->getKey())->where('client_key', $item['key'])->exists();

                if ($already) {
                    $results[] = ['key' => $item['key'], 'status' => 'duplicate', 'message' => null];

                    continue;
                }

                $at = $this->moment($item['at']);

                try {
                    $game->refresh();
                    $this->apply($game, $item['kind'], $item['payload'], $by, $at);

                    SheetCommand::query()->create([
                        'organization_id' => $game->organization_id,
                        'game_id' => $game->getKey(),
                        'client_key' => $item['key'],
                        'kind' => $item['kind'],
                        'user_id' => $by->getKey(),
                        'occurred_at' => $at,
                    ]);

                    $results[] = ['key' => $item['key'], 'status' => 'applied', 'message' => null];
                } catch (InvalidArgumentException $exception) {
                    $blocked = true;
                    $results[] = ['key' => $item['key'], 'status' => 'rejected', 'message' => $exception->getMessage()];
                }
            }

            return $results;
        });
    }

    /** Hora do lance: a do aparelho (alinhada ao relógio do servidor), mas nunca no futuro. */
    private function moment(int $atMs): Carbon
    {
        $at = Carbon::createFromTimestampMs($atMs);

        return $at->greaterThan(now()) ? now() : $at;
    }

    /** @param  array<string, mixed>  $payload */
    private function apply(Game $game, string $kind, array $payload, User $by, Carbon $at): void
    {
        $userId = (int) $by->getKey();

        match ($kind) {
            'event' => $this->event($game, $payload, $userId, $at),
            'point' => $this->point($game, $payload, $userId, $at),
            'period' => $this->period->handle($game, (bool) ($payload['start'] ?? false), (int) ($payload['period'] ?? 0), $userId, $at),
            'clock' => $this->period->pause($game, (bool) ($payload['pause'] ?? false), $userId, $at),
            'void' => $this->voidEvent($game, $payload, $userId, $at),
            'finish' => $this->finish->handle($game, $at),
            default => throw new InvalidArgumentException('Tipo de lançamento desconhecido.'),
        };
    }

    /** @param  array<string, mixed>  $payload */
    private function event(Game $game, array $payload, int $by, Carbon $at): void
    {
        $type = GameEventType::tryFrom((string) ($payload['type'] ?? ''));

        if ($type === null || ! filled($payload['entry_id'] ?? null)) {
            throw new InvalidArgumentException('Lançamento inválido: faltam dados.');
        }

        $this->record->handle(
            $game,
            $type,
            (string) $payload['entry_id'],
            filled($payload['person_id'] ?? null) ? (string) $payload['person_id'] : null,
            filled($payload['secondary_person_id'] ?? null) ? (string) $payload['secondary_person_id'] : null,
            (int) ($payload['period'] ?? 1),
            isset($payload['minute']) && $payload['minute'] !== '' ? (int) $payload['minute'] : null,
            $by,
            $at,
        );
    }

    /** @param  array<string, mixed>  $payload */
    private function point(Game $game, array $payload, int $by, Carbon $at): void
    {
        $entryId = match ($payload['side'] ?? null) {
            'home' => $game->home_entry_id,
            'away' => $game->away_entry_id,
            default => throw new InvalidArgumentException('Lançamento inválido: lado desconhecido.'),
        };

        if ($entryId === null) {
            throw new InvalidArgumentException('O jogo ainda não tem os dois confrontantes definidos.');
        }

        $this->record->handle($game, GameEventType::Point, $entryId, null, null, 1, null, $by, $at);
    }

    /** @param  array<string, mixed>  $payload */
    private function voidEvent(Game $game, array $payload, int $by, Carbon $at): void
    {
        $event = GameEvent::query()->where('game_id', $game->getKey())->find((string) ($payload['event_id'] ?? ''));

        if ($event === null) {
            throw new InvalidArgumentException('O lançamento a anular não existe mais neste jogo.');
        }

        $this->void->handle($game, $event, $by, $at);
    }
}
