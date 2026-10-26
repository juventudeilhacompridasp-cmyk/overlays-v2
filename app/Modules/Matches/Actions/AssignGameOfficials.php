<?php

namespace App\Modules\Matches\Actions;

use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Enums\OfficialRole;
use App\Modules\Matches\Mail\OfficialAssignmentNotice;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameOfficial;
use App\Modules\Matches\Models\Official;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Support\CommunicationSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Throwable;

/**
 * Define a escala de um jogo: uma pessoa por função, ninguém em duas funções no mesmo jogo. Funções que não vêm no pedido
 * ficam como estão; vir vazio tira a pessoa. Quem entrou na escala recebe um e-mail (se tem e-mail e a organização não
 * desligou o aviso); falha de e-mail nunca desfaz a escala.
 */
final class AssignGameOfficials
{
    private const LOCKED = [GameStatus::Finished, GameStatus::Walkover, GameStatus::Cancelled];

    /**
     * @param  array<string, string|null>  $roles  função (`OfficialRole`) => id do árbitro/mesário (vazio = vaga livre)
     * @return int Quantas pessoas novas entraram na escala.
     */
    public function handle(Game $game, array $roles): int
    {
        if (in_array($game->status, self::LOCKED, true)) {
            throw new InvalidArgumentException('Este jogo já foi encerrado ou cancelado: a escala não muda mais.');
        }

        $wanted = [];
        foreach ($roles as $role => $officialId) {
            if (OfficialRole::tryFrom((string) $role) === null) {
                throw new InvalidArgumentException('Função desconhecida na escala.');
            }

            $wanted[(string) $role] = ($officialId === null || $officialId === '') ? null : (string) $officialId;
        }

        $existing = GameOfficial::query()->where('game_id', $game->getKey())->get()->keyBy(fn (GameOfficial $row) => $row->role->value);
        $final = [];
        foreach (OfficialRole::cases() as $role) {
            $final[$role->value] = array_key_exists($role->value, $wanted) ? $wanted[$role->value] : $existing->get($role->value)?->official_id;
        }

        $chosen = array_values(array_filter($final));
        if (count($chosen) !== count(array_unique($chosen))) {
            throw new InvalidArgumentException('Uma pessoa não pode ter duas funções no mesmo jogo.');
        }

        $officials = Official::query()->whereIn('id', $chosen)->get()->keyBy('id');
        foreach ($final as $roleValue => $officialId) {
            if ($officialId === null) {
                continue;
            }

            $official = $officials->get($officialId);
            $unchanged = $existing->get($roleValue)?->official_id === $officialId;

            if ($official === null) {
                throw new InvalidArgumentException('Há alguém na escala que não está cadastrado.');
            }

            if (! $official->active && ! $unchanged) {
                throw new InvalidArgumentException("{$official->name} está inativo no cadastro.");
            }
        }

        /** @var list<GameOfficial> $added */
        $added = [];

        DB::transaction(function () use ($game, $final, $existing, &$added): void {
            foreach ($final as $roleValue => $officialId) {
                $current = $existing->get($roleValue);

                if ($current !== null && $current->official_id === $officialId) {
                    continue;
                }

                $current?->delete();

                if ($officialId !== null) {
                    // Quem mudou de função no mesmo jogo libera a vaga antiga antes (unique game x pessoa).
                    GameOfficial::query()->where('game_id', $game->getKey())->where('official_id', $officialId)->delete();

                    $added[] = GameOfficial::query()->create([
                        'organization_id' => $game->organization_id,
                        'game_id' => $game->getKey(),
                        'official_id' => $officialId,
                        'role' => $roleValue,
                    ]);
                }
            }
        });

        $this->notify($game, $added);

        return count($added);
    }

    /** @param  list<GameOfficial>  $added */
    private function notify(Game $game, array $added): void
    {
        $organization = CurrentOrganization::get();

        if ($added === [] || $organization === null || ! CommunicationSettings::enabled('official_assignment', $organization)) {
            return;
        }

        $game->loadMissing(['homeEntry.team', 'awayEntry.team', 'venue', 'division.season.competition']);
        $label = ($game->homeEntry?->team->name ?? 'A definir').' x '.($game->awayEntry?->team->name ?? 'A definir');
        $when = $game->scheduled_at === null ? 'A definir' : $game->scheduled_at->format('d/m/Y').' às '.$game->scheduled_at->format('H:i');

        foreach ($added as $assignment) {
            try {
                $official = $assignment->official;

                if ($official->email === null || $official->email === '') {
                    continue;
                }

                Mail::to($official->email)->queue(new OfficialAssignmentNotice(
                    $official->name,
                    $assignment->role->label(),
                    $label,
                    $game->division->season->competition->name.' · '.$game->division->name,
                    $when,
                    $game->venue_id === null ? 'A definir' : $game->venue->name,
                ));

                $assignment->update(['notified_at' => now()]);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
