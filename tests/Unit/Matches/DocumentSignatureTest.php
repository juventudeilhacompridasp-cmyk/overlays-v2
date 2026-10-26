<?php

use App\Modules\Matches\Support\DocumentSignature;

it('gera um hash sha-256 determinístico para o PDF', function (): void {
    expect(DocumentSignature::hash('%PDF-1.7 exemplo'))
        ->toBe('a896606c29021873a8289c06cb8c3afdf77cf4e92afb4d495f85ead9e9702f0d')
        ->and(DocumentSignature::hash('%PDF-1.7 exemplo'))
        ->toBe(DocumentSignature::hash('%PDF-1.7 exemplo'))
        ->and(DocumentSignature::hash('%PDF-1.7 alterado'))
        ->not->toBe(DocumentSignature::hash('%PDF-1.7 exemplo'));
});
