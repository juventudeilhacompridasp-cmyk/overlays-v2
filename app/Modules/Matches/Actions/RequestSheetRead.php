<?php

namespace App\Modules\Matches\Actions;

use App\Modules\Matches\Enums\SheetUploadStatus;
use App\Modules\Matches\Jobs\ReadSheetUpload;
use App\Modules\Matches\Models\GameSheetRead;
use App\Modules\Matches\Models\GameSheetUpload;
use App\Modules\Matches\Reading\SheetImportSettings;
use InvalidArgumentException;

/**
 * Pede a leitura de uma súmula enviada (ADR 0004). A estruturada (CSV/XLSX) é livre; a por IA (foto/PDF) exige o interruptor da
 * organização ligado, chave da plataforma e respeita os limites por jogo e por mês. A leitura em si roda em fila.
 */
final class RequestSheetRead
{
    public function handle(GameSheetUpload $upload): void
    {
        if (! in_array($upload->status, [SheetUploadStatus::Uploaded, SheetUploadStatus::Failed, SheetUploadStatus::Read], true)) {
            throw new InvalidArgumentException($upload->status === SheetUploadStatus::Reading ? 'Esta súmula já está sendo lida.' : 'Esta súmula não pode mais ser lida de novo.');
        }

        $reader = $upload->kind === 'structured' ? 'structured' : 'vision';

        if ($reader === 'vision') {
            $this->ensureAiAllowed($upload);
        }

        $upload->update(['status' => SheetUploadStatus::Reading, 'error' => null]);

        GameSheetRead::query()->create(['game_id' => $upload->game_id, 'upload_id' => $upload->getKey(), 'reader' => $reader]);

        ReadSheetUpload::dispatch($upload->getKey(), (string) $upload->organization_id);
    }

    private function ensureAiAllowed(GameSheetUpload $upload): void
    {
        if (! SheetImportSettings::aiEnabled()) {
            throw new InvalidArgumentException('A leitura por IA está desligada nesta organização. Envie uma planilha (CSV/XLSX) ou peça a um administrador para ligá-la.');
        }

        if (! SheetImportSettings::aiAvailable()) {
            throw new InvalidArgumentException('A leitura por IA ainda não foi configurada neste ambiente. Envie uma planilha (CSV/XLSX).');
        }

        if (SheetImportSettings::usedForGame($upload->game_id) >= SheetImportSettings::maxReadsPerGame()) {
            throw new InvalidArgumentException('Este jogo já usou todas as leituras por IA permitidas ('.SheetImportSettings::maxReadsPerGame().').');
        }

        if (SheetImportSettings::usedThisMonth() >= SheetImportSettings::monthlyLimit()) {
            throw new InvalidArgumentException('O limite mensal de leituras por IA da organização foi atingido. Tente no próximo mês ou fale com a plataforma.');
        }
    }
}
