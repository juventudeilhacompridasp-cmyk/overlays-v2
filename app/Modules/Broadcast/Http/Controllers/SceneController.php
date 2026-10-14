<?php

namespace App\Modules\Broadcast\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Broadcast\Actions\SaveOverlayScene;
use App\Modules\Broadcast\Actions\UpdateOverlayChannel;
use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Broadcast\Models\OverlayScene;
use App\Modules\Tenancy\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/** Cenas dos overlays: salvar e excluir é de quem gerencia competições; aplicar é operação ao vivo. */
class SceneController extends Controller
{
    public function store(Request $request, SaveOverlayScene $save): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('overlay_scenes', 'name')->where('organization_id', CurrentOrganization::id())],
            'channel_id' => ['required', 'string', Rule::exists('overlay_channels', 'id')->where('organization_id', CurrentOrganization::id())],
        ], [], ['name' => 'nome', 'channel_id' => 'canal']);

        try {
            $save->fromChannel(OverlayChannel::query()->findOrFail($data['channel_id']), trim($data['name']));
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['name' => $exception->getMessage()]);
        }

        return back()->with('success', 'Cena salva.');
    }

    public function apply(OverlayChannel $channel, OverlayScene $scene, UpdateOverlayChannel $update): RedirectResponse
    {
        $update->handle($channel, $scene->config);

        return back()->with('success', "Cena “{$scene->name}” no ar.");
    }

    public function destroy(OverlayScene $scene): RedirectResponse
    {
        $scene->delete();

        return back()->with('success', 'Cena excluída.');
    }
}
