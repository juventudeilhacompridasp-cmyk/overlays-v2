<?php

namespace Database\Seeders;

use App\Modules\Competitions\Enums\EntryStatus;
use App\Modules\Competitions\Enums\Gender;
use App\Modules\Competitions\Enums\SeasonStatus;
use App\Modules\Competitions\Enums\StageType;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Division;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Competitions\Models\Season;
use App\Modules\Competitions\Models\Stage;
use App\Modules\Matches\Actions\FinishGame;
use App\Modules\Matches\Actions\RegisterWalkover;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameEvent;
use App\Modules\Matches\Models\Venue;
use App\Modules\Teams\Enums\RosterRole;
use App\Modules\Teams\Models\Person;
use App\Modules\Teams\Models\RosterEntry;
use App\Modules\Teams\Models\Team;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Database\Seeder;

/**
 * Campeonato de demonstração (pontos corridos, 6 equipes, turno único) com resultados, cartões e um W.O.,
 * para ver classificação e suspensões. Determinístico e idempotente (não duplica se já existir).
 * Uso: php artisan db:seed --class=DemoCompetitionSeeder
 */
class DemoCompetitionSeeder extends Seeder
{
    private const TEAMS = ['Atlético Ilha', 'Real Cananéia', 'Esporte Clube Jacupiranga', 'Unidos do Rio', 'Juventude Registro', 'Estrela do Vale'];

    public function run(): void
    {
        $organization = Organization::query()->firstOrCreate(
            ['slug' => config('platform.default_organization_slug')],
            ['name' => 'Organização padrão'],
        );

        CurrentOrganization::using($organization, function (): void {
            if (Competition::query()->where('slug', 'campeonato-demo')->exists()) {
                $this->command?->info('Campeonato de demonstração já existe.');

                return;
            }

            $competition = Competition::query()->create(['name' => 'Campeonato Amador (demonstração)', 'slug' => 'campeonato-demo']);
            $season = Season::query()->create([
                'competition_id' => $competition->id, 'name' => '2026', 'year' => 2026,
                'status' => SeasonStatus::Active, 'rules' => null,
            ]);
            $division = Division::query()->create([
                'season_id' => $season->id, 'name' => 'Primeira Divisão', 'category' => 'Adulto', 'gender' => Gender::Male,
            ]);
            $stage = Stage::query()->create([
                'division_id' => $division->id, 'name' => 'Fase única', 'type' => StageType::League, 'sequence' => 1,
            ]);
            $venue = Venue::query()->create(['name' => 'Estádio Municipal', 'city' => 'Registro']);

            $entries = [];
            foreach (self::TEAMS as $index => $name) {
                $team = Team::query()->create(['name' => $name, 'short_name' => strtoupper(substr($name, 0, 3))]);
                $entry = Entry::query()->create([
                    'division_id' => $division->id, 'team_id' => $team->id, 'seed' => $index + 1, 'status' => EntryStatus::Confirmed,
                ]);

                for ($n = 1; $n <= 6; $n++) {
                    $person = Person::query()->create(['name' => "Atleta {$n} - {$name}"]);
                    RosterEntry::query()->create([
                        'entry_id' => $entry->id, 'person_id' => $person->id, 'role' => RosterRole::Athlete, 'shirt_number' => $n,
                    ]);
                }

                $entries[] = $entry->load('rosterEntries');
            }

            $this->playRoundRobin($stage, $venue, $entries);
        });
    }

    /**
     * @param  list<Entry>  $entries
     */
    private function playRoundRobin(Stage $stage, Venue $venue, array $entries): void
    {
        $number = 1;
        $round = 1;
        $start = now()->startOfDay()->subDays(30)->setTime(15, 0);
        $finish = app(FinishGame::class);

        for ($i = 0; $i < count($entries); $i++) {
            for ($j = $i + 1; $j < count($entries); $j++) {
                $game = Game::query()->create([
                    'stage_id' => $stage->id, 'division_id' => $stage->division_id,
                    'home_entry_id' => $entries[$i]->id, 'away_entry_id' => $entries[$j]->id,
                    'venue_id' => $venue->id, 'round' => $round, 'number' => $number,
                    'scheduled_at' => $start->copy()->addDays($number),
                ]);

                // Um W.O. determinístico: a equipe 6 não comparece contra a 3.
                if ($i === 2 && $j === 5) {
                    app(RegisterWalkover::class)->handle($game, $entries[$i]);
                } else {
                    $this->recordMatch($game, $entries[$i], $entries[$j], $number);
                    $finish->handle($game);
                }

                $number++;
                $round = intdiv($number - 1, 3) + 1;
            }
        }
    }

    private function recordMatch(Game $game, Entry $home, Entry $away, int $number): void
    {
        $homeGoals = ($number * 3 + 1) % 4;
        $awayGoals = ($number * 2 + 2) % 3;
        $minute = 5;

        foreach ([[$home, $homeGoals], [$away, $awayGoals]] as [$entry, $goals]) {
            for ($g = 0; $g < $goals; $g++) {
                $this->event($game, $entry, GameEventType::Goal, $minute += 7, $entry->rosterEntries[($number + $g) % 6]->person_id);
            }
        }

        // O atleta 1 de cada equipe leva amarelo com frequência (gera suspensões por acúmulo); vermelho de vez em quando.
        foreach ([$home, $away] as $entry) {
            if (($number + $entry->seed) % 2 === 0) {
                $this->event($game, $entry, GameEventType::YellowCard, 30 + $number, $entry->rosterEntries[0]->person_id);
            }

            if (($number * $entry->seed) % 11 === 0) {
                $this->event($game, $entry, GameEventType::RedCard, 70, $entry->rosterEntries[1]->person_id);
            }
        }
    }

    private function event(Game $game, Entry $entry, GameEventType $type, int $minute, string $personId): void
    {
        GameEvent::query()->create([
            'game_id' => $game->id,
            'type' => $type,
            'period' => $minute > 45 ? 2 : 1,
            'minute' => min($minute, 90),
            'entry_id' => $entry->id,
            'person_id' => $personId,
        ]);
    }
}
