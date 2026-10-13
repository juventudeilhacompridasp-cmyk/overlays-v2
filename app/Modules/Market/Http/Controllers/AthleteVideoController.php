<?php

namespace App\Modules\Market\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Market\Actions\ManageMarketVideos;
use App\Modules\Market\Models\MarketMedia;
use App\Modules\Market\Models\MarketProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/** Vídeos de jogadas do próprio perfil (ADR 0008, parte 4): só links externos validados. */
class AthleteVideoController extends Controller
{
    public function store(Request $request, ManageMarketVideos $videos): RedirectResponse
    {
        $data = $request->validate(['url' => ['required', 'string', 'max:300']], [], ['url' => 'link do vídeo']);

        $profile = MarketProfile::query()->where('user_id', $request->user()->getKey())->firstOrFail();

        try {
            $videos->add($profile, $data['url']);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['url' => $exception->getMessage()]);
        }

        return back()->with('success', 'Vídeo adicionado.');
    }

    public function destroy(Request $request, MarketMedia $video, ManageMarketVideos $videos): RedirectResponse
    {
        abort_unless($video->profile->user_id === $request->user()->getKey(), 404);

        $videos->remove($video);

        return back()->with('success', 'Vídeo removido.');
    }
}
