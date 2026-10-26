<?php

namespace App\Modules\Matches\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameMedia;
use App\Modules\Market\Support\VideoLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class GameMediaController extends Controller
{
    public function index(Game $game): Response
    {
        $game->load(['homeEntry.team', 'awayEntry.team']);

        return Inertia::render('Panel/Games/Media', [
            'game' => ['id' => $game->getKey(), 'home' => $game->homeEntry?->team->name, 'away' => $game->awayEntry?->team->name],
            'media' => GameMedia::query()->where('game_id', $game->getKey())->orderBy('sort')->orderBy('created_at')->get()->map(fn (GameMedia $item) => [
                'id' => $item->getKey(), 'title' => $item->title, 'url' => $item->url, 'provider' => $item->provider, 'published' => $item->published,
            ]),
        ]);
    }

    public function store(Request $request, Game $game): RedirectResponse
    {
        $data = $request->validate(['url' => ['required', 'url:https', 'max:500'], 'title' => ['nullable', 'string', 'max:120']]);
        $link = VideoLink::parse($data['url']);

        if ($link === null) {
            return back()->withErrors(['url' => 'Use um link HTTPS do YouTube, Vimeo ou Instagram.']);
        }

        GameMedia::query()->create([
            'game_id' => $game->getKey(), 'organization_id' => $game->organization_id, 'type' => 'video',
            'title' => $data['title'] ?? null, 'provider' => $link['provider'], 'external_id' => $link['external_id'], 'url' => $link['url'],
            'sort' => (int) GameMedia::query()->where('game_id', $game->getKey())->max('sort') + 1, 'published' => false,
        ]);

        return back()->with('success', 'Vídeo adicionado. Publique-o quando estiver pronto.');
    }

    public function toggle(GameMedia $media): RedirectResponse
    {
        $media->update(['published' => ! $media->published]);

        return back()->with('success', $media->published ? 'Mídia publicada.' : 'Mídia retirada do portal.');
    }

    public function destroy(GameMedia $media): RedirectResponse
    {
        $media->delete();

        return back()->with('success', 'Mídia removida.');
    }
}
