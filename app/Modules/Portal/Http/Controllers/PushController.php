<?php

namespace App\Modules\Portal\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Matches\Models\Game;
use App\Modules\Portal\Models\PushSubscription;
use App\Modules\Teams\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Avisos push para o público: pedir ou cancelar aviso de um jogo ou de uma equipe, sem login. Só jogos de edição
 * publicada e equipes com inscrição confirmada em edição publicada. Recurso `push` + chaves VAPID.
 */
class PushController extends Controller
{
    public function subscribe(Request $request): JsonResponse
    {
        $data = $this->validated($request, withKeys: true);
        $this->ensureTarget($data['kind'], $data['id']);

        PushSubscription::query()->updateOrCreate(
            ['target_type' => $data['kind'], 'target_id' => $data['id'], 'endpoint_hash' => PushSubscription::hash($data['endpoint'])],
            ['endpoint' => $data['endpoint'], 'p256dh' => $data['keys']['p256dh'], 'auth' => $data['keys']['auth']],
        );

        return response()->json(['subscribed' => true], 201);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $data = $this->validated($request, withKeys: false);

        PushSubscription::query()
            ->where('target_type', $data['kind'])->where('target_id', $data['id'])
            ->where('endpoint_hash', PushSubscription::hash($data['endpoint']))
            ->delete();

        return response()->json(['subscribed' => false]);
    }

    /** @return array{kind: string, id: string, endpoint: string, keys: array{p256dh: string, auth: string}} */
    private function validated(Request $request, bool $withKeys): array
    {
        /** @var array{kind: string, id: string, endpoint: string, keys: array{p256dh: string, auth: string}} $data */
        $data = $request->validate([
            'kind' => ['required', Rule::in([PushSubscription::GAME, PushSubscription::TEAM])],
            'id' => ['required', 'string', 'size:26'],
            'endpoint' => ['required', 'url:https', 'max:1000'],
            'keys' => [$withKeys ? 'required' : 'nullable', 'array'],
            'keys.p256dh' => [$withKeys ? 'required' : 'nullable', 'string', 'max:200'],
            'keys.auth' => [$withKeys ? 'required' : 'nullable', 'string', 'max:100'],
        ]);

        return $data;
    }

    private function ensureTarget(string $kind, string $id): void
    {
        if ($kind === PushSubscription::GAME) {
            $game = Game::query()->find($id);
            abort_unless($game !== null && $game->division->season->isPublished(), 404);

            return;
        }

        abort_unless(Team::query()->whereKey($id)->whereHas('entries', fn ($entry) => $entry->whereHas('division.season', fn ($season) => $season->published()))->exists(), 404);
    }
}
