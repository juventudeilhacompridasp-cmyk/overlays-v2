<?php

namespace App\Modules\ApiDocs\Support;

/**
 * Histórico de mudanças da referência das APIs (ADR 0007, parte 3), mantido à mão em `resources/api-docs/changelog.php`
 * (mais recente primeiro). A versão corrente é a da primeira entrada e vai em `info.version` da especificação; mudou rota,
 * parâmetro ou resposta ⇒ o PR acrescenta uma entrada (o teste confere formato, ordem e a versão da especificação).
 */
final class ApiChangelog
{
    /**
     * @return list<array{version: string, date: string, changes: list<string>}>
     */
    public static function entries(): array
    {
        /** @var list<array{version: string, date: string, changes: list<string>}> $entries */
        $entries = require resource_path('api-docs/changelog.php');

        return $entries;
    }

    public static function current(): string
    {
        return self::entries()[0]['version'];
    }
}
