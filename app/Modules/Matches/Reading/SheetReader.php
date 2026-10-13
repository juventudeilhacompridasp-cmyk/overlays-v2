<?php

namespace App\Modules\Matches\Reading;

/** Lê o conteúdo de uma súmula enviada e devolve o que entendeu no esquema fixo `ExtractedSheet` (ADR 0004). */
interface SheetReader
{
    /**
     * @param  array{sport?: string, home?: ?string, away?: ?string}  $context  só o necessário (esporte e nomes das equipes)
     *
     * @throws SheetReadFailed
     */
    public function read(string $contents, string $mime, array $context = []): ExtractedSheet;

    /** Nome gravado em `game_sheet_reads.reader` (`structured` ou `vision`). */
    public function name(): string;
}
