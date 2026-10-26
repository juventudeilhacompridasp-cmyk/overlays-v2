<?php

namespace App\Modules\Matches\Actions;

use App\Models\User;
use App\Modules\Matches\Models\Game;
use App\Modules\Matches\Models\GameDocumentSignature;
use App\Modules\Matches\Models\GameSheetClosure;
use App\Modules\Tenancy\Support\SheetClosingSettings;
use InvalidArgumentException;

/**
 * Fecha a súmula de um jogo encerrado (ou com W.O.): registra quem fechou, quando e quantas assinaturas da súmula já estavam
 * confirmadas. O mínimo de assinaturas é da organização (`SheetClosingSettings`). Só conta assinatura feita depois do encerramento
 * atual, então reabrir e encerrar de novo exige um novo ciclo. Não altera o jogo.
 */
final class CloseGameSheet
{
    public function handle(Game $game, ?User $by = null, ?string $notes = null): GameSheetClosure
    {
        if (! $game->status->isDecided()) {
            throw new InvalidArgumentException('Só é possível fechar a súmula de um jogo encerrado ou com W.O.');
        }

        if ($game->sheetClosure !== null) {
            throw new InvalidArgumentException('A súmula deste jogo já está fechada.');
        }

        $signed = self::signedCount($game);
        $required = SheetClosingSettings::all()['min_signatures'];

        if ($signed < $required) {
            $missing = $required - $signed;

            throw new InvalidArgumentException("Faltam {$missing} assinatura(s) confirmada(s) da súmula para fechá-la (mínimo de {$required}).");
        }

        $notes = $notes === null ? null : trim($notes);

        return GameSheetClosure::query()->create([
            'organization_id' => $game->organization_id,
            'game_id' => $game->getKey(),
            'closed_by' => $by?->getKey(),
            'closed_by_name' => $by?->name ?? 'Sistema',
            'closed_at' => now(),
            'signatures_count' => $signed,
            'notes' => $notes === '' ? null : mb_substr((string) $notes, 0, 500),
        ]);
    }

    public static function signedCount(Game $game): int
    {
        return GameDocumentSignature::query()
            ->where('game_id', $game->getKey())
            ->where('document_type', 'sheet')
            ->where('status', 'signed')
            ->when($game->finished_at !== null, fn ($query) => $query->where('signed_at', '>=', $game->finished_at))
            ->count();
    }
}
