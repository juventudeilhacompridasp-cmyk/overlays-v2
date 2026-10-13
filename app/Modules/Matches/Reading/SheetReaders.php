<?php

namespace App\Modules\Matches\Reading;

use App\Modules\Matches\Models\GameSheetUpload;

/** Escolhe o leitor do arquivo: planilhas pelo estruturado (sem IA), foto/PDF pela visão. Resolvido pelo container (testes trocam). */
final class SheetReaders
{
    public static function for(GameSheetUpload $upload): SheetReader
    {
        return $upload->kind === 'structured' ? app(StructuredSheetReader::class) : app(VisionSheetReader::class);
    }
}
