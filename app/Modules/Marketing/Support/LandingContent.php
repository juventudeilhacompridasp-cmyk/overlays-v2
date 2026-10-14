<?php

namespace App\Modules\Marketing\Support;

use App\Modules\Competitions\Enums\Sport;
use App\Modules\Tenancy\Enums\Feature;
use App\Modules\Tenancy\Enums\Plan;

/**
 * Conteúdo da landing page. Pacotes, recursos e esportes vêm do próprio código (`Plan`, `Feature`, `Sport`): a página nunca
 * repete essas listas à mão. Preço fica de fora enquanto a cobrança automática estiver fora do MVP ("sob consulta").
 */
class LandingContent
{
    /** Assuntos do formulário de contato (chave => rótulo). */
    public const INTERESTS = [
        'competition' => 'Organizar campeonatos',
        'broadcast' => 'Transmitir com overlays no OBS',
        'both' => 'As duas coisas',
        'other' => 'Outro assunto',
    ];

    /** @return array<string, mixed> */
    public static function build(): array
    {
        return [
            'hero' => [
                'title' => 'Seu campeonato e sua transmissão, no mesmo lugar',
                'subtitle' => 'Gestão de competições com placar, escalação e tabela ao vivo prontos para o OBS. Do cadastro das equipes à súmula, do portal público ao operador na ilha de edição.',
            ],
            'pillars' => [
                ['title' => 'Overlays ao vivo para OBS', 'text' => 'Placar, lances, escalação com esquema tático, tabela, pênaltis, estatísticas e patrocinadores em uma URL fixa por tela. Animações leves, feitas para rodar liso no OBS.'],
                ['title' => 'Modo operador', 'text' => 'Uma tela só, sem rolagem, com tudo o que vai ao ar. Atalhos de teclado, cenas prontas e blocos que você arrasta e redimensiona do seu jeito.'],
                ['title' => 'Competições completas', 'text' => 'Pontos corridos, grupos e mata-mata com ida e volta, regulamento parametrizável, súmula em tempo real e fila offline para quando a internet cai.'],
                ['title' => 'Portal público e widgets', 'text' => 'Página de cada competição, equipe e jogo com placar ao vivo, estatísticas, calendário e widgets para colocar no site de quem quiser.'],
                ['title' => 'Inscrição e carteirinha', 'text' => 'As equipes se inscrevem por um link, você aprova pelo painel e cada atleta ganha carteirinha com QR code verificável.'],
                ['title' => 'Área do gestor de equipe', 'text' => 'O gestor monta a escalação e o esquema tático, acompanha suspensões e recebe avisos de horário e local por e-mail.'],
                ['title' => 'Artes das partidas', 'text' => 'Jogo do dia, resultado, escalação, classificação e artilharia em qualquer proporção, com a identidade visual da sua organização.'],
                ['title' => 'Marca própria', 'text' => 'Portal com o seu nome, cores e logotipo, e domínio próprio quando o pacote permitir.'],
            ],
            'gallery' => self::gallery(),
            'overlays' => [
                'Placar completo com comemoração de gol',
                'Faixas de lances: gol, cartões e substituições',
                'Escalação em grade, esquema tático e jogador a jogador',
                'Tabela, chaveamento e resumo do jogo',
                'Disputa de pênaltis cobrança a cobrança',
                'Estatísticas, pré-jogo e comparativo',
                'Barra de patrocinadores 1500 x 200 em loop',
                'Overlays livres desenhados no editor',
            ],
            'sports' => array_map(fn (Sport $sport) => $sport->label(), Sport::cases()),
            'plans' => self::plans(),
            'matrix' => self::matrix(),
            'privacy' => [
                'O CPF nunca é guardado em claro: só uma impressão digital irreversível para evitar cadastro duplicado.',
                'Documentos e fotos pessoais ficam em armazenamento privado, abertos só por link assinado e temporário.',
                'Dados de menores e de disciplina não são públicos; nomes de atletas menores aparecem abreviados.',
                'Overlays e páginas públicas nunca recebem documentos, nascimento, cartões de outras equipes ou notas táticas.',
            ],
            'faq' => [
                ['q' => 'Preciso instalar alguma coisa?', 'a' => 'Não. Tudo roda no navegador. No OBS você adiciona cada tela como uma fonte de navegador usando a URL do canal.'],
                ['q' => 'Funciona sem internet na quadra?', 'a' => 'A súmula guarda os lançamentos no aparelho e envia tudo quando a conexão volta. O app do mesário pode ser instalado no celular.'],
                ['q' => 'Quais esportes são suportados?', 'a' => 'Hoje: '.implode(', ', array_map(fn (Sport $sport) => $sport->label(), Sport::cases())).', cada um com as regras de pontuação, relógio e faltas do esporte.'],
                ['q' => 'Posso usar meu próprio domínio e minha marca?', 'a' => 'Sim, nos pacotes que incluem domínio próprio e marca própria. O DNS é apontado pela sua equipe com a nossa orientação.'],
                ['q' => 'Quanto custa?', 'a' => 'Os pacotes são montados por organização e a cobrança é combinada diretamente. Envie uma mensagem e respondemos com a proposta.'],
                ['q' => 'Meus dados e os dos atletas estão seguros?', 'a' => 'Seguimos a LGPD desde o desenho: dados mínimos, documentos privados, nada de CPF em claro e páginas públicas sem dados pessoais.'],
            ],
            'interests' => array_map(fn (string $key, string $label) => ['value' => $key, 'label' => $label], array_keys(self::INTERESTS), self::INTERESTS),
        ];
    }

    /**
     * Galeria da landing: prints REAIS do produto com dados fictícios, gerados por `npm run marketing:shots`
     * (`scripts/marketing/shots.config.mjs` é a lista de origem; o teste confere que os nomes batem e que os arquivos existem).
     *
     * @return list<array{src: string, title: string, text: string, alt: string}>
     */
    public static function gallery(): array
    {
        $items = [
            'placar' => ['Placar ao vivo', 'Placar completo com relógio, gols de cada jogador e a identidade da sua organização.'],
            'gol' => ['Faixa de lance', 'Gol, cartão e substituição saem sozinhos da súmula, com o placar na hora.'],
            'escalacao' => ['Escalação e esquema tático', 'Grade, esquema tático com fotos e jogador a jogador, com animações leves para o OBS.'],
            'tabela' => ['Classificação', 'Tabela sempre atualizada, com destaque para as equipes do jogo no ar.'],
            'penaltis' => ['Disputa de pênaltis', 'Cobrança a cobrança, com a vez de cada equipe e a morte súbita.'],
            'patrocinio' => ['Barra de patrocinadores', 'Barra 1500 x 200 com vários banners em loop, no ritmo que você escolher.'],
            'operador' => ['Modo operador', 'Tudo o que vai ao ar numa tela só, sem rolagem, com blocos que você move e redimensiona.'],
        ];

        $gallery = [];

        foreach ($items as $name => [$title, $text]) {
            $gallery[] = ['src' => "/marketing/{$name}.webp", 'title' => $title, 'text' => $text, 'alt' => "{$title}: exemplo com dados fictícios"];
        }

        return $gallery;
    }

    /** @return list<array{key: string, label: string, features: list<string>}> */
    public static function plans(): array
    {
        return array_map(fn (Plan $plan) => [
            'key' => $plan->value,
            'label' => $plan->label(),
            'features' => array_map(fn (Feature $feature) => $feature->label(), $plan->features()),
        ], Plan::cases());
    }

    /**
     * Matriz de recursos: o núcleo vale para todos; cada recurso diz em quais pacotes entra; os sob demanda ficam fora de
     * todos e são liberados pela plataforma.
     *
     * @return list<array{label: string, plans: array<string, bool>, on_demand: bool}>
     */
    public static function matrix(): array
    {
        $rows = [];

        foreach (['Competições, súmula e portal público', 'Painel do organizador e Modo operador'] as $core) {
            $rows[] = ['label' => $core, 'plans' => array_fill_keys(array_map(fn (Plan $plan) => $plan->value, Plan::cases()), true), 'on_demand' => false];
        }

        foreach (Feature::cases() as $feature) {
            $rows[] = [
                'label' => $feature->label(),
                'plans' => array_combine(
                    array_map(fn (Plan $plan) => $plan->value, Plan::cases()),
                    array_map(fn (Plan $plan) => in_array($feature, $plan->features(), true), Plan::cases()),
                ),
                'on_demand' => $feature->isOptIn(),
            ];
        }

        return $rows;
    }

    /**
     * Dados estruturados (schema.org) da página: o produto e quem o publica. Só o que a página já mostra.
     *
     * @return array<string, mixed>
     */
    public static function jsonLd(string $url): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => (string) config('app.name'),
            'url' => $url,
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web',
            'inLanguage' => 'pt-BR',
            'description' => 'Plataforma de gestão de competições esportivas com overlays ao vivo para OBS, portal público e súmula em tempo real.',
            'featureList' => ['Overlays para OBS', 'Súmula em tempo real', 'Portal público', 'Inscrição online', 'Gestão de campeonatos'],
        ];
    }
}
