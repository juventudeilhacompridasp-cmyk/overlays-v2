<?php

/*
| Semeia, num banco SQLite novo, um jogo de futebol com as duas escalações salvas e um operador de jogo, para o teste de
| navegador da fila offline (tests/e2e/offline-queue.mjs). Imprime um JSON com o que o teste precisa.
|
| Uso: php tests/e2e/seed-sheet.php   (com DB_CONNECTION=sqlite e DB_DATABASE apontando para o arquivo, já migrado)
*/

use App\Models\User;
use App\Modules\Competitions\Enums\EntryStatus;
use App\Modules\Competitions\Enums\StageType;
use App\Modules\Competitions\Models\Division;
use App\Modules\Competitions\Models\Entry;
use App\Modules\Competitions\Models\Stage;
use App\Modules\Matches\Actions\GenerateStageFixtures;
use App\Modules\Matches\Actions\SaveGameLineup;
use App\Modules\Matches\Models\Game;
use App\Modules\Teams\Models\RosterEntry;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$organization = Organization::query()->firstOrCreate(['slug' => config('platform.default_organization_slug')], ['name' => 'Organização padrão']);
CurrentOrganization::set($organization);

$division = Division::factory()->create();
$stage = Stage::factory()->for($division)->create(['type' => StageType::League]);

foreach ([1, 2] as $seed) {
    Entry::factory()->for($division)->create(['seed' => $seed, 'status' => EntryStatus::Confirmed]);
}

app(GenerateStageFixtures::class)->handle($stage);

$game = Game::query()->where('stage_id', $stage->id)->firstOrFail();

$players = fn (Entry $entry): array => RosterEntry::factory()->count(14)->sequence(fn ($s) => ['shirt_number' => $s->index + 1])
    ->create(['entry_id' => $entry->id])->pluck('person_id')->all();

$home = $players($game->homeEntry);
$away = $players($game->awayEntry);

app(SaveGameLineup::class)->handle($game, $game->homeEntry, array_slice($home, 0, 11), array_slice($home, 11));
app(SaveGameLineup::class)->handle($game, $game->awayEntry, array_slice($away, 0, 11), array_slice($away, 11));

$user = User::factory()->create(['email' => 'mesario@e2e.test', 'password' => 'password']);
Membership::query()->create(['organization_id' => $organization->getKey(), 'user_id' => $user->getKey(), 'role' => Role::Operator]);

echo json_encode(['game' => $game->getKey(), 'home' => $game->homeEntry->team->name, 'email' => 'mesario@e2e.test', 'password' => 'password']);
