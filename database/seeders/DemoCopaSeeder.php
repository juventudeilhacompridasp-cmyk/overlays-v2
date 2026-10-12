<?php

namespace Database\Seeders;

use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Competitions\Enums\EntryStatus;
use App\Modules\Competitions\Enums\Gender;
use App\Modules\Competitions\Enums\SeasonStatus;
use App\Modules\Competitions\Enums\StageType;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Division;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Competitions\Models\Season;
use App\Modules\Competitions\Models\Stage;
use App\Modules\Matches\Actions\ChangeGamePeriod;
use App\Modules\Matches\Actions\GenerateStageFixtures;
use App\Modules\Matches\Actions\RecordGameEvent;
use App\Modules\Matches\Actions\RecordQuickResult;
use App\Modules\Matches\Actions\SaveGameLineup;
use App\Modules\Matches\Enums\GameEventType;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\Venue;
use App\Modules\Teams\Enums\RosterRole;
use App\Modules\Teams\Models\Person;
use App\Modules\Teams\Models\RosterEntry;
use App\Modules\Teams\Models\Team;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Copa de demonstração: 8 equipes com elenco, fase de grupos (2 grupos gerados pelo sorteio do sistema,
 * rodadas 1 e 2 encerradas, 3ª rodada agendada e um jogo AO VIVO com escalação e lances),
 * uma divisão com mata-mata (semifinais e final) e um canal de overlay apontado para o jogo ao vivo.
 * Idempotente. Uso: php artisan db:seed --class=DemoCopaSeeder
 */
class DemoCopaSeeder extends Seeder
{
    private const TEAMS = [
        ['Atlético Ilha Comprida', 'AIC', 'Ilha Comprida', '#0B5FA5'],
        ['Sport Club Pedrinhas', 'SCP', 'Ilha Comprida', '#1E8E3E'],
        ['Praia Grande FC', 'PGF', 'Ilha Comprida', '#F9A825'],
        ['Guarani da Barra', 'GUA', 'Cananéia', '#C62828'],
        ['Iguape Esporte Clube', 'IEC', 'Iguape', '#6A1B9A'],
        ['União Jureia', 'UJU', 'Iguape', '#00838F'],
        ['Juventude do Mar', 'JDM', 'Ilha Comprida', '#E65100'],
        ['Estrela do Valo Grande', 'EVG', 'Iguape', '#37474F'],
    ];

    private const FIRST_NAMES = [
        'Lucas', 'Gabriel', 'Mateus', 'Pedro', 'Rafael', 'Bruno', 'Thiago', 'Felipe', 'Diego', 'Rodrigo',
        'Leandro', 'Vinícius', 'André', 'Carlos', 'João', 'Marcelo', 'Caio', 'Renan', 'Igor', 'Henrique',
    ];

    private const LAST_NAMES = [
        'Silva', 'Souza', 'Oliveira', 'Santos', 'Pereira', 'Lima', 'Costa', 'Ribeiro', 'Almeida', 'Carvalho',
        'Gomes', 'Martins', 'Rocha', 'Barbosa', 'Moreira', 'Nunes', 'Teixeira', 'Cardoso', 'Ramos', 'Pinto',
    ];

    public function run(): void
    {
        $organization = Organization::query()->firstOrCreate(
            ['slug' => config('platform.default_organization_slug')],
            ['name' => 'Organização padrão'],
        );

        CurrentOrganization::using($organization, fn () => DB::transaction(function (): void {
            if (Competition::query()->where('slug', 'copa-ilha-demo')->exists()) {
                return;
            }

            $competition = Competition::query()->create(['name' => 'Copa Ilha Comprida (demonstração)', 'slug' => 'copa-ilha-demo']);
            $season = Season::query()->create([
                'competition_id' => $competition->id, 'name' => '2026', 'year' => 2026,
                'status' => SeasonStatus::Active, 'rules' => null,
            ]);

            $venues = [
                Venue::query()->create(['name' => 'Estádio Municipal Ilha Comprida', 'city' => 'Ilha Comprida']),
                Venue::query()->create(['name' => 'Campo do Pedrinhas', 'city' => 'Ilha Comprida']),
            ];

            // Fase de grupos: 8 equipes sorteadas em 2 grupos pelo gerador de tabela do sistema.
            $groupsDivision = Division::query()->create([
                'season_id' => $season->id, 'name' => 'Fase de grupos', 'category' => 'Adulto', 'gender' => Gender::Male,
            ]);
            $groupsStage = Stage::query()->create([
                'division_id' => $groupsDivision->id, 'name' => 'Grupos', 'type' => StageType::Groups, 'sequence' => 1,
            ]);

            $entries = [];
            $people = [];
            foreach (self::TEAMS as $index => [$name, $short, $city, $color]) {
                $team = Team::query()->create([
                    'name' => $name, 'short_name' => $short, 'city' => $city, 'primary_color' => $color,
                ]);
                $entry = Entry::query()->create([
                    'division_id' => $groupsDivision->id, 'team_id' => $team->id, 'seed' => $index + 1, 'status' => EntryStatus::Confirmed,
                ]);

                $people[$team->id] = $this->roster($entry, $index);
                $entries[] = $entry;
            }

            app(GenerateStageFixtures::class)->handle($groupsStage, doubleRound: false, groups: 2);
            $this->playGroups($groupsStage, $venues);

            // Mata-mata: semifinais e final entre as quatro melhores cabeças de chave (mesmos elencos).
            $knockoutDivision = Division::query()->create([
                'season_id' => $season->id, 'name' => 'Taça Ouro', 'category' => 'Adulto', 'gender' => Gender::Male,
            ]);
            $knockoutStage = Stage::query()->create([
                'division_id' => $knockoutDivision->id, 'name' => 'Mata-mata', 'type' => StageType::Knockout, 'sequence' => 1,
            ]);

            foreach (array_slice($entries, 0, 4) as $index => $groupEntry) {
                $entry = Entry::query()->create([
                    'division_id' => $knockoutDivision->id, 'team_id' => $groupEntry->team_id,
                    'seed' => $index + 1, 'status' => EntryStatus::Confirmed,
                ]);

                foreach ($people[$groupEntry->team_id] as $position => $personId) {
                    RosterEntry::query()->create([
                        'entry_id' => $entry->id, 'person_id' => $personId,
                        'role' => $position === 0 ? RosterRole::Coach : RosterRole::Athlete,
                        'shirt_number' => $position === 0 ? null : $position,
                    ]);
                }
            }

            app(GenerateStageFixtures::class)->handle($knockoutStage, legs: 1);
            $day = now()->addDays(14)->setTime(16, 0);
            foreach ($knockoutStage->games()->orderBy('number')->get() as $game) {
                $game->update(['venue_id' => $venues[0]->id, 'scheduled_at' => $day->copy()]);
                $day->addDays(3);
            }
        }));
    }

    /**
     * Cria o elenco da equipe: 1 técnico + 15 atletas com camisa 1..15.
     *
     * @return list<string> ids das pessoas (posição 0 = técnico; posição n = atleta de camisa n)
     */
    private function roster(Entry $entry, int $teamIndex): array
    {
        $ids = [];

        $coach = Person::query()->create(['name' => 'Prof. '.$this->name($teamIndex, 99)]);
        RosterEntry::query()->create(['entry_id' => $entry->id, 'person_id' => $coach->id, 'role' => RosterRole::Coach]);
        $ids[] = $coach->id;

        for ($shirt = 1; $shirt <= 15; $shirt++) {
            $full = $this->name($teamIndex, $shirt);
            $person = Person::query()->create([
                'name' => $full,
                'nickname' => explode(' ', $full)[0],
                'birth_date' => now()->subYears(18 + ($shirt * 7 + $teamIndex) % 14)->subDays($shirt * 13),
            ]);
            RosterEntry::query()->create([
                'entry_id' => $entry->id, 'person_id' => $person->id, 'role' => RosterRole::Athlete, 'shirt_number' => $shirt,
            ]);
            $ids[$shirt] = $person->id;
        }

        return $ids;
    }

    private function name(int $teamIndex, int $n): string
    {
        $first = self::FIRST_NAMES[($teamIndex * 7 + $n * 3) % count(self::FIRST_NAMES)];
        $last = self::LAST_NAMES[($teamIndex * 5 + $n * 11) % count(self::LAST_NAMES)];

        return "{$first} {$last}";
    }

    /**
     * Rodadas 1 e 2 encerradas (resultado rápido), 3ª rodada agendada; 1 jogo da 3ª rodada fica ao vivo.
     *
     * @param  list<Venue>  $venues
     */
    private function playGroups(Stage $stage, array $venues): void
    {
        $games = $stage->games()->orderBy('round')->orderBy('number')->get();
        $quick = app(RecordQuickResult::class);
        $live = null;
        $slot = 0;

        foreach ($games as $game) {
            $venue = $venues[$slot % count($venues)];

            if ($game->round <= 2) {
                $game->update([
                    'venue_id' => $venue->id,
                    'scheduled_at' => now()->subDays((3 - $game->round) * 7)->setTime(15 + ($slot % 2) * 2, 0),
                ]);
                $quick->handle($game, ($slot * 3 + 1) % 4, ($slot * 2 + 2) % 3);
            } else {
                $game->update([
                    'venue_id' => $venue->id,
                    'scheduled_at' => now()->addDays(1 + intdiv($slot, 2))->setTime(15 + ($slot % 2) * 2, 0),
                ]);
                $live ??= $game;
            }

            $slot++;
        }

        if ($live !== null) {
            $live->update(['scheduled_at' => now()->subMinutes(20)]);
            $this->playLive($live->refresh());

            $channel = OverlayChannel::query()->create([
                'name' => 'Transmissão principal',
                'token' => Str::random(40),
                'game_id' => $live->id,
                'layers' => ['lineup' => false, 'standings' => false],
            ]);
            $this->command->info('Overlays (OBS): '.$channel->overlayUrl('placar'));
        }
    }

    private function playLive(Game $game): void
    {
        $home = $game->homeEntry()->with('rosterEntries')->firstOrFail();
        $away = $game->awayEntry()->with('rosterEntries')->firstOrFail();
        $save = app(SaveGameLineup::class);

        foreach ([$home, $away] as $entry) {
            $athletes = $entry->rosterEntries->where('role', RosterRole::Athlete)->sortBy('shirt_number')->values();
            $coach = $entry->rosterEntries->firstWhere('role', RosterRole::Coach);

            $save->handle(
                $game,
                $entry,
                $athletes->take(11)->pluck('person_id')->all(),
                $athletes->slice(11)->pluck('person_id')->all(),
                $coach ? [$coach->person_id] : [],
            );
        }

        app(ChangeGamePeriod::class)->handle($game, start: true, period: 1);

        $record = app(RecordGameEvent::class);
        $starter = fn ($entry, int $shirt) => $entry->rosterEntries->firstWhere('shirt_number', $shirt)->person_id;

        $record->handle($game, GameEventType::Goal, $home->id, $starter($home, 9), period: 1, minute: 12);
        $record->handle($game, GameEventType::YellowCard, $away->id, $starter($away, 5), period: 1, minute: 23);
        $record->handle($game, GameEventType::Goal, $away->id, $starter($away, 10), period: 1, minute: 31);
        $record->handle($game, GameEventType::Goal, $home->id, $starter($home, 7), period: 1, minute: 38);
    }
}
