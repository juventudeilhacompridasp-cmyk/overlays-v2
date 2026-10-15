<?php

use App\Modules\Competitions\Enums\Sport;
use App\Modules\Matches\Actions\GenerateStageFixtures;
use App\Modules\Matches\Enums\GameStatus;
use App\Modules\Matches\Models\Game;
use App\Modules\Portal\Jobs\SendGamePush;
use App\Modules\Portal\Models\PushSubscription;
use App\Modules\Portal\Push\PushSender;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    CurrentOrganization::set(Organization::factory()->create(['slug' => config('platform.default_organization_slug')]));
    config(['webpush.public_key' => 'chave-publica', 'webpush.private_key' => 'chave-privada']);
    fakeSender(); // nenhum teste fala com um serviço de push de verdade
});

function pushGame(Sport $sport = Sport::Football): Game
{
    ['stage' => $stage] = fixtureDivision(2);
    $stage->division->season->competition->update(['sport' => $sport]);
    app(GenerateStageFixtures::class)->handle($stage);
    $game = Game::query()->where('stage_id', $stage->id)->firstOrFail();
    publishSeason($game);

    return $game->fresh();
}

function subscriptionData(string $kind, string $id, string $endpoint = 'https://push.example.com/abc'): array
{
    return ['kind' => $kind, 'id' => $id, 'endpoint' => $endpoint, 'keys' => ['p256dh' => 'p256', 'auth' => 'auth']];
}

/** Sender falso: guarda o que seria enviado e devolve as inscrições "expiradas" configuradas. */
function fakeSender(array $expiredEndpoints = []): object
{
    $sender = new class($expiredEndpoints) implements PushSender
    {
        public array $sent = [];

        public function __construct(private array $expired) {}

        public function send(iterable $subscriptions, array $payload): array
        {
            $ids = [];
            foreach ($subscriptions as $subscription) {
                $this->sent[] = ['endpoint' => $subscription->endpoint, 'payload' => $payload];
                if (in_array($subscription->endpoint, $this->expired, true)) {
                    $ids[] = (string) $subscription->getKey();
                }
            }

            return $ids;
        }
    };
    app()->instance(PushSender::class, $sender);

    return $sender;
}

describe('subscriptions', function () {
    it('subscribes to a game and is idempotent', function () {
        $game = pushGame();

        $this->postJson('/push/assinar', subscriptionData('game', $game->id))->assertCreated();
        $this->postJson('/push/assinar', subscriptionData('game', $game->id))->assertCreated();
        $this->postJson('/push/assinar', subscriptionData('game', $game->id, 'https://push.example.com/outro'))->assertCreated();

        expect(PushSubscription::query()->count())->toBe(2);
    });

    it('subscribes to a team with a published season and cancels', function () {
        $game = pushGame();
        $teamId = $game->homeEntry->team_id;

        $this->postJson('/push/assinar', subscriptionData('team', $teamId))->assertCreated();
        expect(PushSubscription::query()->where('target_type', 'team')->count())->toBe(1);

        $this->postJson('/push/cancelar', ['kind' => 'team', 'id' => $teamId, 'endpoint' => 'https://push.example.com/abc'])->assertOk();
        expect(PushSubscription::query()->count())->toBe(0);
    });

    it('validates the payload and the target', function () {
        $game = pushGame();

        $this->postJson('/push/assinar', [])->assertUnprocessable();
        $this->postJson('/push/assinar', subscriptionData('game', $game->id, 'http://inseguro.example.com/x'))->assertUnprocessable();
        $this->postJson('/push/assinar', ['kind' => 'game', 'id' => $game->id, 'endpoint' => 'https://push.example.com/abc'])->assertUnprocessable();
        $this->postJson('/push/assinar', subscriptionData('outro', $game->id))->assertUnprocessable();
        $this->postJson('/push/assinar', subscriptionData('game', '01HZZZZZZZZZZZZZZZZZZZZZZZ'))->assertNotFound();

        $game->division->season->update(['status' => 'draft']);
        $this->postJson('/push/assinar', subscriptionData('game', $game->id))->assertNotFound();
        expect(PushSubscription::query()->count())->toBe(0);
    });

    it('needs the push feature', function () {
        $game = pushGame();
        CurrentOrganization::get()->update(['plan' => 'standard']);

        $this->postJson('/push/assinar', subscriptionData('game', $game->id))->assertNotFound();
    });
});

describe('who sees the button', function () {
    it('passes the public key only with keys and the feature', function () {
        $game = pushGame();

        $this->get("/jogos/{$game->id}")->assertInertia(fn ($page) => $page->where('push_key', 'chave-publica'));
        $this->get('/equipes/'.$game->homeEntry->team_id)->assertInertia(fn ($page) => $page->where('push_key', 'chave-publica'));

        config(['webpush.public_key' => null]);
        $this->get("/jogos/{$game->id}")->assertInertia(fn ($page) => $page->where('push_key', null));

        config(['webpush.public_key' => 'chave-publica']);
        CurrentOrganization::get()->update(['plan' => 'standard']);
        $this->get("/jogos/{$game->id}")->assertInertia(fn ($page) => $page->where('push_key', null));
    });
});

describe('notifications', function () {
    it('queues a notice when the game starts, when the score changes and when it ends', function () {
        $game = pushGame();
        Queue::fake();

        $game->update(['status' => GameStatus::InProgress]);
        Queue::assertPushed(SendGamePush::class, fn ($job) => $job->kind === SendGamePush::STARTED && $job->gameId === $game->id);

        $game->update(['home_score' => 1]);
        Queue::assertPushed(SendGamePush::class, fn ($job) => $job->kind === SendGamePush::SCORE);

        $game->update(['status' => GameStatus::Finished]);
        Queue::assertPushed(SendGamePush::class, fn ($job) => $job->kind === SendGamePush::FINISHED);
        expect(Queue::pushed(SendGamePush::class))->toHaveCount(3);
    });

    it('does not queue anything without keys or for point-by-point basketball scores', function () {
        $game = pushGame(Sport::Basketball);
        Queue::fake();

        $game->update(['status' => GameStatus::InProgress]);
        Queue::assertPushed(SendGamePush::class, fn ($job) => $job->kind === SendGamePush::STARTED);

        $game->update(['home_score' => 2]);
        expect(Queue::pushed(SendGamePush::class))->toHaveCount(1); // cesta não gera aviso

        config(['webpush.private_key' => null]);
        $game->update(['status' => GameStatus::Finished]);
        expect(Queue::pushed(SendGamePush::class))->toHaveCount(1);
    });

    it('sends public text only, once per device, to the game and team followers', function () {
        $game = pushGame();
        $sender = app(PushSender::class);
        $team = $game->homeEntry->team_id;
        foreach ([['game', $game->id, 'https://p.example.com/a'], ['team', $team, 'https://p.example.com/a'], ['team', $team, 'https://p.example.com/b'], ['team', $game->awayEntry->team_id, 'https://p.example.com/c']] as [$kind, $id, $endpoint]) {
            PushSubscription::query()->create(['target_type' => $kind, 'target_id' => $id, 'endpoint' => $endpoint, 'endpoint_hash' => PushSubscription::hash($endpoint), 'p256dh' => 'x', 'auth' => 'y']);
        }
        $game->forceFill(['status' => GameStatus::InProgress, 'home_score' => 1, 'away_score' => 0])->saveQuietly();

        (new SendGamePush(CurrentOrganization::id(), $game->id, SendGamePush::SCORE, 'https://site/jogos/x'))->handle($sender);

        expect(collect($sender->sent)->pluck('endpoint')->sort()->values()->all())->toBe(['https://p.example.com/a', 'https://p.example.com/b', 'https://p.example.com/c'])
            ->and($sender->sent[0]['payload']['title'])->toBe('GOL!')
            ->and($sender->sent[0]['payload']['body'])->toBe("{$game->homeEntry->team->name} 1 x 0 {$game->awayEntry->team->name}")
            ->and($sender->sent[0]['payload']['url'])->toBe('https://site/jogos/x');
    });

    it('titles start and end, and the volleyball set', function () {
        $game = pushGame(Sport::Volleyball);
        $sender = app(PushSender::class);
        PushSubscription::query()->create(['target_type' => 'game', 'target_id' => $game->id, 'endpoint' => 'https://p.example.com/a', 'endpoint_hash' => PushSubscription::hash('https://p.example.com/a'), 'p256dh' => 'x', 'auth' => 'y']);

        foreach ([SendGamePush::STARTED => 'Começou!', SendGamePush::SCORE => 'Set encerrado', SendGamePush::FINISHED => 'Fim de jogo'] as $kind => $title) {
            (new SendGamePush(CurrentOrganization::id(), $game->id, $kind, 'https://site/j'))->handle($sender);
        }

        expect(collect($sender->sent)->pluck('payload.title')->all())->toBe(['Começou!', 'Set encerrado', 'Fim de jogo']);
    });

    it('drops the subscriptions the push service refused and stays silent for draft seasons', function () {
        $game = pushGame();
        $sender = fakeSender(['https://p.example.com/morta']);
        Queue::fake(); // só o job chamado à mão
        foreach (['https://p.example.com/viva', 'https://p.example.com/morta'] as $endpoint) {
            PushSubscription::query()->create(['target_type' => 'game', 'target_id' => $game->id, 'endpoint' => $endpoint, 'endpoint_hash' => PushSubscription::hash($endpoint), 'p256dh' => 'x', 'auth' => 'y']);
        }

        (new SendGamePush(CurrentOrganization::id(), $game->id, SendGamePush::STARTED, 'https://site/j'))->handle($sender);
        expect(PushSubscription::query()->pluck('endpoint')->all())->toBe(['https://p.example.com/viva']);

        $sender->sent = [];
        $game->division->season->update(['status' => 'draft']);
        (new SendGamePush(CurrentOrganization::id(), $game->id, SendGamePush::STARTED, 'https://site/j'))->handle($sender);
        expect($sender->sent)->toBe([]);
    });

    it('keeps the game requests for the retention days set by the platform', function () {
        $game = pushGame();
        PushSubscription::query()->create(['target_type' => 'game', 'target_id' => $game->id, 'endpoint' => 'https://p.example.com/a', 'endpoint_hash' => PushSubscription::hash('https://p.example.com/a'), 'p256dh' => 'x', 'auth' => 'y']);
        $game->forceFill(['status' => GameStatus::Finished, 'finished_at' => now()->subDays(2)])->saveQuietly();

        config(['platform.push_retention_days' => 5]);
        Artisan::call('push:prune');
        expect(PushSubscription::query()->count())->toBe(1);

        config(['platform.push_retention_days' => 1]);
        Artisan::call('push:prune');
        expect(PushSubscription::query()->count())->toBe(0);
    });

    it('prunes game subscriptions of games finished more than a day ago, keeping team ones', function () {
        $game = pushGame();
        $other = $game->homeEntry->team_id;
        foreach ([['game', $game->id], ['team', $other]] as [$kind, $id]) {
            PushSubscription::query()->create(['target_type' => $kind, 'target_id' => $id, 'endpoint' => 'https://p.example.com/a', 'endpoint_hash' => PushSubscription::hash('https://p.example.com/a'), 'p256dh' => 'x', 'auth' => 'y']);
        }

        Artisan::call('push:prune');
        expect(PushSubscription::query()->count())->toBe(2);

        $game->forceFill(['status' => GameStatus::Finished, 'finished_at' => now()->subDays(2)])->saveQuietly();
        Artisan::call('push:prune');

        expect(PushSubscription::query()->pluck('target_type')->all())->toBe(['team']);
    });
});

it('is shipped with service worker handlers for push and notification clicks', function () {
    $worker = (string) file_get_contents(public_path('sw.js'));

    expect($worker)->toContain("addEventListener('push'")->and($worker)->toContain("addEventListener('notificationclick'");
});
