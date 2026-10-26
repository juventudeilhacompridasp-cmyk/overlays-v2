<?php

namespace App\Modules\Matches\Actions;

use App\Modules\Matches\Mail\GameSignatureInvitation;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameDocumentSignature;
use App\Modules\Matches\Support\DocumentSignature;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

final class GameSignatureActions
{
    /** @return array{signature: GameDocumentSignature, token: string} */
    public function invite(Game $game, string $documentType, string $document, string $name, string $role, string $email): array
    {
        $token = Str::random(64);
        $signature = GameDocumentSignature::query()->create([
            'organization_id' => $game->organization_id,
            'game_id' => $game->getKey(),
            'document_type' => $documentType,
            'document_hash' => DocumentSignature::hash($document),
            'signer_name' => $name,
            'signer_role' => $role,
            'method' => 'email_link',
            'status' => 'pending',
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addHours(24),
        ]);

        Mail::to($email)->queue(new GameSignatureInvitation($name, $role, route('game-signatures.confirm', $token)));

        return ['signature' => $signature, 'token' => $token];
    }

    public function confirm(string $token, Request $request): ?GameDocumentSignature
    {
        $signature = GameDocumentSignature::query()->where('token_hash', hash('sha256', $token))->first();

        if ($signature === null || $signature->status !== 'pending' || ($signature->expires_at !== null && Carbon::parse((string) $signature->expires_at)->isPast())) {
            return null;
        }

        $signature->update([
            'status' => 'signed',
            'signed_at' => now(),
            'token_hash' => null,
            'ip_hash' => $request->ip() === null ? null : hash('sha256', $request->ip()),
            'user_agent_hash' => $request->userAgent() === null ? null : hash('sha256', $request->userAgent()),
        ]);

        return $signature->fresh();
    }
}
