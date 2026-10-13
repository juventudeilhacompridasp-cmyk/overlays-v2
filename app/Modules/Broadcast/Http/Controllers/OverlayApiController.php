<?php

namespace App\Modules\Broadcast\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Broadcast\Actions\UpdateOverlayChannel;
use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Broadcast\Support\OverlayControlRules;
use App\Modules\Broadcast\Support\OverlayState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OverlayApiController extends Controller
{
    public function update(Request $request, OverlayChannel $channel, UpdateOverlayChannel $update): JsonResponse
    {
        $token = $request->bearerToken();
        abort_unless($token !== null && $channel->acceptsControlToken($token), 401, 'Chave de controle inválida.');

        $update->handle($channel, $request->validate(OverlayControlRules::for($channel)));
        $channel->refresh();

        return response()->json(['data' => OverlayState::for($channel)])->header('Cache-Control', 'no-store');
    }
}
