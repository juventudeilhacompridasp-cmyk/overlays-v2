<?php

namespace App\Console\Commands;

use App\Modules\Competitions\Actions\CalculateDiscipline;
use App\Modules\Competitions\Actions\CalculateStandings;
use App\Modules\Competitions\Enums\StageType;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Competitions\Models\Stage;
use App\Modules\Teams\Models\Person;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('competitions:standings {stage? : ID da fase de pontos corridos (padrão: a primeira encontrada)}')]
#[Description('Mostra a classificação de uma fase e os atletas suspensos por cartões (para conferir o motor de regras)')]
class ShowStandings extends Command
{
    public function handle(CalculateStandings $standings, CalculateDiscipline $discipline): int
    {
        $organization = Organization::query()->where('slug', config('platform.default_organization_slug'))->first();

        if ($organization === null) {
            $this->error('Organização padrão não encontrada. Rode o seed: php artisan db:seed');

            return self::FAILURE;
        }

        CurrentOrganization::set($organization);

        $stage = $this->argument('stage')
            ? Stage::query()->find($this->argument('stage'))
            : Stage::query()->where('type', StageType::League)->orderBy('created_at')->first();

        if ($stage === null) {
            $this->error('Nenhuma fase de pontos corridos encontrada. Rode: php artisan db:seed --class=DemoCompetitionSeeder');

            return self::FAILURE;
        }

        $this->info("{$stage->division->season->competition->name} — {$stage->division->name} — {$stage->name}");

        $rows = $standings->forStage($stage);

        $this->table(
            ['#', 'Equipe', 'Pts', 'J', 'V', 'E', 'D', 'GP', 'GC', 'SG', 'CA', 'CV', 'Empate*'],
            array_map(fn ($item) => [
                $item->row->position, $item->entry->team->name, $item->row->points, $item->row->played,
                $item->row->wins, $item->row->draws, $item->row->losses, $item->row->goalsFor,
                $item->row->goalsAgainst, $item->row->goalDifference(), $item->row->yellowCards,
                $item->row->redCards, $item->row->tied ? 'sorteio' : '',
            ], $rows),
        );
        $this->line('* "sorteio": empate que nenhum critério calculável resolve.');

        $suspended = [];
        foreach ($rows as $item) {
            /** @var Entry $entry */
            $entry = $item->entry;
            $tracker = $discipline->forEntry($entry);

            foreach ($tracker->suspended() as $personId => $games) {
                $suspended[] = [Person::query()->find($personId)?->name ?? $personId, $entry->team->name, $games];
            }
        }

        $this->newLine();
        $this->info('Suspensos para o próximo jogo (cartões):');
        $suspended === []
            ? $this->line('Ninguém suspenso.')
            : $this->table(['Atleta', 'Equipe', 'Jogos restantes'], $suspended);

        return self::SUCCESS;
    }
}
