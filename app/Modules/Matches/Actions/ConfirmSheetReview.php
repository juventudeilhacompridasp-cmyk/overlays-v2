<?php

namespace App\Modules\Matches\Actions;

use App\Modules\Matches\Enums\SheetUploadStatus;
use App\Modules\Matches\Models\GameSheetUpload;
use App\Modules\Matches\Reading\ExtractedSheet;
use App\Modules\Matches\Reading\SheetReviewDiff;
use InvalidArgumentException;

/**
 * Confirma a leitura revisada por uma pessoa (ADR 0004). Guarda a versão corrigida e o que mudou; a leitura original
 * (`extracted`) fica intacta. NÃO toca no jogo: a conferência só compara e relata.
 */
final class ConfirmSheetReview
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(GameSheetUpload $upload, array $data, ?int $userId): GameSheetUpload
    {
        if (! in_array($upload->status, [SheetUploadStatus::Read, SheetUploadStatus::Reviewed], true) || $upload->extracted === null) {
            throw new InvalidArgumentException('Esta súmula ainda não foi lida ou não pode mais ser revisada.');
        }

        $data['confidence'] = $upload->extracted['confidence'] ?? null;
        $reviewed = ExtractedSheet::fromArray($data)->toArray();

        $upload->update([
            'status' => SheetUploadStatus::Reviewed,
            'reviewed' => $reviewed,
            'review_changes' => SheetReviewDiff::changes($upload->extracted, $reviewed),
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
        ]);

        return $upload;
    }

    public function discard(GameSheetUpload $upload): void
    {
        if (in_array($upload->status, [SheetUploadStatus::Reading, SheetUploadStatus::Discarded], true)) {
            throw new InvalidArgumentException('Esta súmula não pode ser descartada agora.');
        }

        $upload->update(['status' => SheetUploadStatus::Discarded]);
    }
}
