<?php

namespace App\Modules\Help\Support;

use App\Modules\Help\Models\HelpNote;

/**
 * As notas da organização atual como artigos da central de ajuda (slug `nota-{id}`), para entrarem na mesma busca, no mesmo
 * filtro de acesso e no mesmo renderizador seguro. Sem permissão nem recurso: quem é do público da nota a vê.
 */
final class HelpNotes
{
    /**
     * @return list<HelpArticle>
     */
    public static function articles(string $area): array
    {
        if (! in_array($area, ['panel', 'manager'], true)) {
            return [];
        }

        return HelpNote::query()->where('audience', $area)->orderBy('created_at')->get()
            ->map(fn (HelpNote $note) => new HelpArticle(
                slug: self::slug($note),
                audience: $note->audience,
                title: $note->title,
                category: HelpNote::CATEGORY,
                tags: ['nota', 'organização'],
                order: 0,
                ability: null,
                feature: null,
                route: null,
                body: $note->body,
            ))->values()->all();
    }

    public static function slug(HelpNote $note): string
    {
        return 'nota-'.strtolower($note->id);
    }
}
