<?php

namespace App\Modules\Competitions\Support;

use App\Modules\Competitions\Enums\EntryStatus;
use App\Modules\Competitions\Models\Season;
use App\Modules\Teams\Enums\RosterRole;
use App\Modules\Teams\Enums\RosterStatus;
use App\Modules\Teams\Models\RosterEntry;
use Illuminate\Support\Collection;

/** Relatórios do painel (e CSV) sobre os elencos das inscrições confirmadas de uma edição. Nunca traz CPF. */
final class SeasonReports
{
    /**
     * @return Collection<int, RosterEntry>
     */
    private static function members(Season $season): Collection
    {
        return RosterEntry::query()
            ->where('status', RosterStatus::Active)
            ->whereHas('entry', fn ($entry) => $entry->where('status', EntryStatus::Confirmed)->whereHas('division', fn ($division) => $division->where('season_id', $season->getKey())))
            ->with(['person', 'entry.team', 'entry.division'])
            ->get();
    }

    /**
     * Elencos aprovados: um registro por integrante ativo.
     *
     * @return list<array<string, string>>
     */
    public static function rosters(Season $season): array
    {
        $rows = self::members($season)->sortBy([
            fn ($a, $b) => $a->entry->division->name <=> $b->entry->division->name,
            fn ($a, $b) => $a->entry->team->name <=> $b->entry->team->name,
            fn ($a, $b) => ($a->role === RosterRole::Athlete ? 0 : 1) <=> ($b->role === RosterRole::Athlete ? 0 : 1),
            fn ($a, $b) => ($a->shirt_number ?? 999) <=> ($b->shirt_number ?? 999),
            fn ($a, $b) => $a->person->name <=> $b->person->name,
        ]);

        $list = [];

        foreach ($rows as $member) {
            $list[] = [
                'Divisão' => $member->entry->division->name,
                'Equipe' => $member->entry->team->name,
                'Camisa' => $member->shirt_number !== null ? (string) $member->shirt_number : '',
                'Nome' => $member->person->name,
                'Apelido' => (string) $member->person->nickname,
                'Função' => $member->role->label(),
                'Nascimento' => $member->person->birth_date?->format('d/m/Y') ?? '',
                'CPF informado' => $member->person->document_hash !== null ? 'Sim' : 'Não',
                'Foto' => $member->person->photo_path !== null ? 'Sim' : 'Não',
            ];
        }

        return $list;
    }

    /**
     * Pendências de cadastro: integrantes ativos sem nascimento, CPF ou foto.
     *
     * @return list<array<string, string>>
     */
    public static function pending(Season $season): array
    {
        $list = [];

        foreach (self::rosters($season) as $row) {
            $missing = array_values(array_filter([
                $row['Nascimento'] === '' ? 'Data de nascimento' : null,
                $row['CPF informado'] === 'Não' ? 'CPF' : null,
                $row['Foto'] === 'Não' ? 'Foto' : null,
            ]));

            if ($missing !== []) {
                $list[] = [
                    'Divisão' => $row['Divisão'],
                    'Equipe' => $row['Equipe'],
                    'Camisa' => $row['Camisa'],
                    'Nome' => $row['Nome'],
                    'Função' => $row['Função'],
                    'Pendências' => implode(', ', $missing),
                ];
            }
        }

        return $list;
    }

    /** Evita que o Excel execute fórmulas vindas de nomes digitados por terceiros. */
    public static function safeCell(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
