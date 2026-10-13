<?php

use App\Modules\Help\Support\HelpArticle;
use App\Modules\Help\Support\HelpLibrary;
use App\Modules\Help\Support\HelpMarkdown;
use App\Modules\Help\Support\HelpSearch;

function helpArticle(string $slug, string $title, string $body, array $tags = [], string $category = 'Geral'): HelpArticle
{
    return new HelpArticle($slug, 'panel', $title, $category, $tags, 100, null, null, null, $body);
}

describe('library parsing', function () {
    it('reads the header and the body', function () {
        $article = HelpLibrary::parse('panel', 'meu-artigo', "---\ntitle: Meu artigo\ncategory: Teste\ntags: a, b ,c\norder: 5\nability: manage-users\nfeature: overlays\nroute: panel.users.index\n---\n## Seção\n\nTexto.\n");

        expect($article->slug)->toBe('meu-artigo')
            ->and($article->title)->toBe('Meu artigo')
            ->and($article->category)->toBe('Teste')
            ->and($article->tags)->toBe(['a', 'b', 'c'])
            ->and($article->order)->toBe(5)
            ->and($article->ability)->toBe('manage-users')
            ->and($article->feature)->toBe('overlays')
            ->and($article->route)->toBe('panel.users.index')
            ->and($article->body)->toBe("## Seção\n\nTexto.");
    });

    it('ignores files without header or title', function () {
        expect(HelpLibrary::parse('panel', 'x', "só texto\n"))->toBeNull()
            ->and(HelpLibrary::parse('panel', 'x', "---\ncategory: Sem título\n---\ncorpo"))->toBeNull();
    });
});

describe('markdown', function () {
    it('renders structured blocks, never HTML', function () {
        $blocks = HelpMarkdown::render("## Título\n\nTexto com **negrito** e `código`.\n\n- um\n- dois\n\n1. passo\n2. outro\n\n> Aviso <b>x</b>\n\n### Sub", fn () => null);

        expect(array_column($blocks, 'type'))->toBe(['h2', 'p', 'ul', 'ol', 'note', 'h3'])
            ->and($blocks[1]['inline'])->toBe([
                ['t' => 'text', 'v' => 'Texto com '],
                ['t' => 'b', 'v' => 'negrito'],
                ['t' => 'text', 'v' => ' e '],
                ['t' => 'code', 'v' => 'código'],
                ['t' => 'text', 'v' => '.'],
            ])
            ->and($blocks[2]['items'])->toHaveCount(2)
            ->and($blocks[3]['items'])->toHaveCount(2)
            // O texto fica como texto; quem desenha (React) nunca o interpreta como HTML.
            ->and($blocks[4]['inline'][0]['v'])->toBe('Aviso <b>x</b>');
    });

    it('resolves links and turns unknown ones into plain text', function () {
        $resolve = fn (string $target) => $target === 'help:existe' ? '/ajuda/existe' : null;
        $blocks = HelpMarkdown::render('Veja [este](help:existe) e [aquele](help:sumiu) e [fora](https://exemplo.com).', $resolve);

        expect($blocks[0]['inline'][1])->toBe(['t' => 'link', 'v' => 'este', 'href' => '/ajuda/existe'])
            ->and($blocks[0]['inline'][3])->toBe(['t' => 'text', 'v' => 'aquele'])
            ->and($blocks[0]['inline'][5])->toBe(['t' => 'text', 'v' => 'fora']);
    });

    it('joins wrapped lines into one paragraph and strips markup for summaries', function () {
        $blocks = HelpMarkdown::render("linha um\nlinha dois", fn () => null);

        expect($blocks)->toHaveCount(1)
            ->and($blocks[0]['inline'][0]['v'])->toBe('linha um linha dois')
            ->and(HelpMarkdown::plain('**Olá** [mundo](help:x) `a`'))->toBe('Olá mundo a');
    });
});

describe('search', function () {
    $articles = fn () => [
        helpArticle('sumula', 'Lançar a súmula de um jogo', "Registre gols e cartões.\n\n## Substituições\n\nUse o painel.", ['súmula', 'mesário']),
        helpArticle('gestor', 'Convidar o gestor de uma equipe', 'O gestor recebe um e-mail com o convite.', ['gestor', 'convite']),
        helpArticle('portal', 'Portal público', 'A súmula não aparece no portal. Tema e logotipo.', ['portal']),
    ];

    it('ignores accents, case, plurals and filler words', function () use ($articles) {
        $hits = HelpSearch::search($articles(), 'Como LANÇAR a SUMULA?');

        expect($hits[0]['article']->slug)->toBe('sumula');
        expect(array_map(fn ($hit) => $hit['article']->slug, HelpSearch::search($articles(), 'gestores')))->toContain('gestor');
    });

    it('ranks title and tags above the body', function () use ($articles) {
        $hits = HelpSearch::search($articles(), 'súmula');

        expect(array_map(fn ($hit) => $hit['article']->slug, $hits))->toBe(['sumula', 'portal']);
    });

    it('tolerates one typo in long words', function () use ($articles) {
        $hits = HelpSearch::search($articles(), 'convidr gestorr');

        expect($hits[0]['article']->slug)->toBe('gestor');
    });

    it('returns a snippet from the matching line and nothing for empty or unrelated queries', function () use ($articles) {
        $hits = HelpSearch::search($articles(), 'logotipo');

        expect($hits)->toHaveCount(1)
            ->and($hits[0]['snippet'])->toContain('logotipo')
            ->and(HelpSearch::search($articles(), '   '))->toBe([])
            ->and(HelpSearch::search($articles(), 'de da o'))->toBe([])
            ->and(HelpSearch::search($articles(), 'xyzzy'))->toBe([]);
    });
});
