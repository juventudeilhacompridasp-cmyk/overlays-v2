<?php

/*
| Catálogo de descrições das rotas (ADR 0007), indexado pelo NOME da rota. O que não está aqui aparece na tela como
| "sem descrição". Cada resposta pode ter `schema` (subconjunto de JSON Schema: type, properties, required, items,
| enum) e `example`; os testes conferem que o exemplo cumpre o esquema e que o nome existe nas rotas.
*/

$ulid = fn (string $description) => ['type' => 'string', 'description' => $description];
$nullableString = ['type' => ['string', 'null']];
$nullableInt = ['type' => ['integer', 'null']];

$team = [
    'type' => ['object', 'null'],
    'properties' => [
        'name' => ['type' => 'string'],
        'short_name' => $nullableString,
        'logo_url' => $nullableString,
        'color' => $nullableString,
    ],
];

$snapshotSchema = [
    'type' => 'object',
    'description' => 'Retrato PÚBLICO do jogo. Nunca traz cartões, escalação nem dados pessoais; o autor do gol sai pelo nome público (menores abreviados).',
    'required' => ['id', 'status', 'finished', 'home', 'away', 'home_score', 'away_score', 'clock', 'goals', 'server_time_ms'],
    'properties' => [
        'id' => $ulid('ID (ULID) do jogo.'),
        'status' => ['type' => 'string', 'description' => 'scheduled, in_progress, finished, postponed, cancelled, suspended ou walkover.'],
        'status_label' => ['type' => 'string'],
        'finished' => ['type' => 'boolean'],
        'home' => $team,
        'away' => $team,
        'home_score' => ['type' => 'integer', 'description' => 'Gols, pontos ou sets ganhos, conforme `score_model`.'],
        'away_score' => ['type' => 'integer'],
        'home_penalties' => $nullableInt,
        'away_penalties' => $nullableInt,
        'shootout' => ['type' => ['object', 'null'], 'description' => 'Disputa de pênaltis (nula se não houve): cobranças de cada equipe em ordem (`true` convertida, `false` perdida), `goals`, `first`, `next`, `decided`, `winner`, `rounds` e `by_kick` (lançada cobrança a cobrança). Nunca o cobrador.'],
        'scheduled_at' => $nullableString,
        'venue' => $nullableString,
        'competition' => ['type' => 'string'],
        'division' => ['type' => 'string'],
        'stage' => ['type' => 'string'],
        'sport' => ['type' => 'string', 'enum' => ['football', 'futsal', 'volleyball', 'basketball']],
        'score_model' => ['type' => 'string', 'enum' => ['goals', 'sets', 'points']],
        'sets' => ['type' => ['array', 'null']],
        'clock' => ['type' => 'object', 'description' => 'Marcos do relógio; o cliente conta o tempo sozinho a partir de `server_time_ms`.'],
        'quarters' => ['type' => ['array', 'object', 'null']],
        'fouls' => ['type' => ['object', 'null']],
        'timeouts' => ['type' => ['object', 'null'], 'description' => 'Pedidos de tempo que restam a cada equipe no grupo de períodos em andamento (`home`, `away`, `limit`, `period`); null nos esportes que não contam pedidos.'],
        'goals' => [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'required' => ['side', 'own_goal'],
                'properties' => [
                    'side' => ['type' => ['string', 'null'], 'enum' => ['home', 'away', null]],
                    'player' => $nullableString,
                    'minute' => $nullableInt,
                    'period' => ['type' => 'integer'],
                    'own_goal' => ['type' => 'boolean'],
                ],
            ],
        ],
        'server_time_ms' => ['type' => 'integer', 'description' => 'Hora do servidor em milissegundos (para alinhar o relógio do cliente).'],
    ],
];

$snapshotExample = [
    'id' => '01JABCDEF0123456789XYZ0001',
    'status' => 'in_progress',
    'status_label' => 'Em andamento',
    'finished' => false,
    'home' => ['name' => 'Leões da Ilha', 'short_name' => 'LEO', 'logo_url' => null, 'color' => '#047857'],
    'away' => ['name' => 'Águias do Norte', 'short_name' => 'AGU', 'logo_url' => null, 'color' => '#1d4ed8'],
    'home_score' => 1,
    'away_score' => 0,
    'home_penalties' => null,
    'away_penalties' => null,
    'shootout' => null,
    'scheduled_at' => '2026-10-20T19:00:00-03:00',
    'venue' => 'Estádio Municipal',
    'competition' => 'Copa Ilha',
    'division' => 'Principal',
    'stage' => 'Fase de grupos',
    'sport' => 'football',
    'score_model' => 'goals',
    'sets' => null,
    'clock' => ['period' => 1],
    'quarters' => null,
    'fouls' => null,
    'timeouts' => null,
    'goals' => [['side' => 'home', 'player' => 'Fulano', 'minute' => 23, 'period' => 1, 'own_goal' => false]],
    'server_time_ms' => 1792000000000,
];

$notFound = ['description' => 'Edição em rascunho ou jogo de edição não publicada (a rota finge que não existe).'];
$throttle = ['description' => 'Limite de requisições por minuto excedido.'];

return [
    // ───────────── API pública v1 ─────────────
    'api.v1.competitions.index' => [
        'summary' => 'Lista as competições que têm edição publicada',
        'description' => 'Somente leitura e sem autenticação. Cada competição traz as edições publicadas (mais recentes primeiro) e o endereço da edição.',
        'responses' => [
            '200' => [
                'description' => 'Competições com suas edições publicadas.',
                'schema' => [
                    'type' => 'object',
                    'required' => ['data'],
                    'properties' => ['data' => ['type' => 'array', 'items' => [
                        'type' => 'object',
                        'required' => ['id', 'name', 'slug', 'sport', 'seasons'],
                        'properties' => [
                            'id' => $ulid('ID (ULID) da competição.'),
                            'name' => ['type' => 'string'],
                            'slug' => ['type' => 'string'],
                            'sport' => ['type' => 'string'],
                            'seasons' => ['type' => 'array', 'items' => [
                                'type' => 'object',
                                'required' => ['id', 'name', 'year', 'status', 'url'],
                                'properties' => ['id' => ['type' => 'string'], 'name' => ['type' => 'string'], 'year' => ['type' => 'integer'], 'status' => ['type' => 'string'], 'url' => ['type' => 'string']],
                            ]],
                        ],
                    ]]],
                ],
                'example' => ['data' => [['id' => '01JABCDEF0123456789XYZ0010', 'name' => 'Copa Ilha', 'slug' => 'copa-ilha', 'sport' => 'football', 'seasons' => [['id' => '01JABCDEF0123456789XYZ0020', 'name' => 'Copa Ilha 2026', 'year' => 2026, 'status' => 'in_progress', 'url' => 'https://exemplo.test/api/v1/seasons/01JABCDEF0123456789XYZ0020']]]]],
            ],
            '429' => $throttle,
        ],
    ],
    'api.v1.seasons.show' => [
        'summary' => 'Edição publicada: divisões, fases, classificação, rodadas e artilharia',
        'description' => 'Mesmo recorte do portal público: sem cartões, disciplina, escalação, elenco ou documentos. Nomes de atletas seguem `PublicName` (menores abreviados).',
        'parameters' => ['season' => 'ID (ULID) da edição publicada.'],
        'responses' => [
            '200' => [
                'description' => 'A edição completa (trecho do exemplo abreviado).',
                'schema' => [
                    'type' => 'object',
                    'required' => ['data'],
                    'properties' => ['data' => [
                        'type' => 'object',
                        'required' => ['competition', 'season', 'divisions'],
                        'properties' => [
                            'competition' => ['type' => 'object', 'required' => ['id', 'name', 'slug']],
                            'season' => ['type' => 'object', 'required' => ['id', 'name', 'year', 'status']],
                            'divisions' => ['type' => 'array', 'items' => ['type' => 'object', 'required' => ['id', 'name', 'stages', 'scorers']]],
                        ],
                    ]],
                ],
                'example' => ['data' => [
                    'competition' => ['id' => '01JABCDEF0123456789XYZ0010', 'name' => 'Copa Ilha', 'slug' => 'copa-ilha'],
                    'season' => ['id' => '01JABCDEF0123456789XYZ0020', 'name' => 'Copa Ilha 2026', 'year' => 2026, 'status' => 'in_progress', 'status_label' => 'Em andamento', 'starts_on' => '2026-10-01', 'ends_on' => null],
                    'divisions' => [['id' => '01JABCDEF0123456789XYZ0030', 'name' => 'Principal', 'category' => null, 'stages' => [], 'scorers' => []]],
                ]],
            ],
            '404' => $notFound,
            '429' => $throttle,
        ],
    ],
    'api.v1.seasons.stats' => [
        'summary' => 'Estatísticas da edição por divisão',
        'description' => 'Ataque, defesa, forma (últimos 5), maiores vitórias e jogos mais movimentados, calculados só com jogos encerrados em campo (W.O. e abertos ficam de fora). A unidade (gols, pontos ou sets) vem do esporte. Nunca traz cartões nem atletas.',
        'parameters' => ['season' => 'ID (ULID) da edição publicada.'],
        'responses' => [
            '200' => [
                'description' => 'Estatísticas por divisão.',
                'schema' => [
                    'type' => 'object',
                    'required' => ['data'],
                    'properties' => ['data' => [
                        'type' => 'object',
                        'required' => ['unit', 'unit_singular', 'divisions'],
                        'properties' => [
                            'unit' => ['type' => 'string', 'description' => 'Plural da unidade: gols, pontos ou sets.'],
                            'unit_singular' => ['type' => 'string'],
                            'divisions' => ['type' => 'array', 'items' => ['type' => 'object', 'required' => ['id', 'name', 'summary', 'teams']]],
                        ],
                    ]],
                ],
                'example' => ['data' => [
                    'unit' => 'gols',
                    'unit_singular' => 'gol',
                    'divisions' => [['id' => '01JABCDEF0123456789XYZ0030', 'name' => 'Principal', 'summary' => ['games' => 10, 'total' => 27, 'average' => 2.7, 'home_wins' => 5, 'draws' => 2, 'away_wins' => 3], 'best_attack' => null, 'best_defense' => null, 'teams' => [], 'biggest_wins' => []]],
                ]],
            ],
            '404' => $notFound,
            '429' => $throttle,
        ],
    ],
    'api.v1.games.show' => [
        'summary' => 'Retrato público de um jogo (placar, relógio e gols)',
        'parameters' => ['game' => 'ID (ULID) de um jogo de edição publicada.'],
        'responses' => [
            '200' => ['description' => 'O retrato do jogo dentro de `data`.', 'schema' => ['type' => 'object', 'required' => ['data'], 'properties' => ['data' => $snapshotSchema]], 'example' => ['data' => $snapshotExample]],
            '404' => $notFound,
            '429' => $throttle,
        ],
    ],
    'api.v1.overlay-channels.state.update' => [
        'summary' => 'Atualiza o estado e o visual de um canal de overlay',
        'description' => 'Controle privado para automações da transmissão. Aceita atualizações parciais do jogo no ar, camadas, aparência, rotação de patrocinadores, apresentação da escalação e a aplicação de uma cena salva. Use a chave de controle do canal como Bearer; ela é diferente do token público usado pelo OBS.',
        'parameters' => ['channel' => 'ID (ULID) do canal de overlay.'],
        'request' => [
            'schema' => ['type' => 'object', 'properties' => [
                'game_id' => ['type' => ['string', 'null']],
                'scene' => ['type' => 'string', 'description' => 'ID de uma cena da organização: aplica o visual, as camadas, os patrocinadores e a apresentação da escalação guardados nela. Os demais campos enviados junto valem por cima da cena.'],
                'layers' => ['type' => 'object', 'properties' => [
                    'scoreboard' => ['type' => 'boolean'],
                    'lower_third' => ['type' => 'boolean'],
                    'lineup' => ['type' => 'boolean'],
                    'standings' => ['type' => 'boolean'],
                ]],
                'lower_third_settings' => ['type' => 'object', 'properties' => [
                    'style' => ['type' => 'string', 'enum' => ['arena', 'broadcast', 'player', 'minimal', 'slash', 'flash'], 'description' => 'Estilo visual da faixa de lances.'],
                    'animation' => ['type' => 'string', 'enum' => ['slide', 'rise', 'zoom', 'wipe', 'flip', 'bounce', 'fade']],
                    'seconds' => ['type' => 'integer', 'minimum' => 3, 'maximum' => 15],
                    'kinds' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['goal', 'own_goal', 'yellow_card', 'red_card', 'blue_card', 'substitution']], 'description' => 'Quais lances da súmula viram faixa.'],
                    'score' => ['type' => 'boolean', 'description' => 'Mostra o placar na faixa do gol.'],
                    'position' => ['type' => 'string', 'enum' => ['left', 'center', 'right'], 'description' => 'Âncora horizontal da faixa (a faixa de ponta a ponta ignora).'],
                    'x' => ['type' => ['integer', 'null'], 'description' => 'Posição livre horizontal em % da tela; só vale com `y`.'],
                    'y' => ['type' => ['integer', 'null'], 'description' => 'Posição livre vertical em % da tela.'],
                    'text_scale' => ['type' => 'integer', 'minimum' => 50, 'maximum' => 200, 'description' => 'Tamanho do texto da faixa (%).'],
                    'card_mode' => ['type' => 'string', 'enum' => ['banner', 'fullscreen'], 'description' => 'Cartões amarelo, vermelho e azul em faixa ou em cartão grande no centro da tela.'],
                ]],
                'lower_third_show' => ['type' => 'object', 'description' => 'Dispara uma faixa agora (não é guardada). `custom` = título/subtítulo livres; `player` = atleta da escalação (`person_id`); os demais tipos enviam uma amostra com o jogo no ar. Só sai com a tela de lances no ar.', 'properties' => [
                    'kind' => ['type' => 'string', 'enum' => ['custom', 'player', 'goal', 'own_goal', 'yellow_card', 'red_card', 'blue_card', 'substitution']],
                    'title' => ['type' => 'string', 'maxLength' => 60],
                    'subtitle' => ['type' => 'string', 'maxLength' => 80],
                    'person_id' => ['type' => 'string'],
                ]],
                'stoppage_minutes' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 15, 'description' => 'Acréscimos mostrados ao lado do relógio do placar (0 remove). Trocar o jogo no ar zera.'],
                'screens_live' => ['type' => 'object', 'description' => 'Mostra (true) ou oculta (false) ao vivo as telas extras: `pregame` (cartela pré-jogo), `compare` (comparativo de equipes), `cartela` (cartela de transmissão), `summary` (resumo do jogo) e `bracket` (chaveamento do mata-mata) e `social` (chamada para redes sociais) `sponsor_bar` (barra de patrocinadores 1500 x 200) `stats` (estatísticas da partida) e `shootout` (disputa de pênaltis).', 'additionalProperties' => ['type' => 'boolean']],
                'screen_settings' => ['type' => 'object', 'description' => 'Texto da cartela de transmissão. `minutes` (0 a 180) inicia uma contagem regressiva; 0 a remove.', 'properties' => [
                    'title' => ['type' => 'string', 'maxLength' => 60],
                    'subtitle' => ['type' => 'string', 'maxLength' => 80],
                    'minutes' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 180],
                    'social' => ['type' => 'object', 'description' => 'Chamada para redes sociais: até 4 linhas (`items[].label`/`value`) e `url` https para o QR code.', 'properties' => [
                        'items' => ['type' => 'array', 'maxItems' => 4, 'items' => ['type' => 'object', 'properties' => ['label' => ['type' => 'string', 'maxLength' => 20], 'value' => ['type' => 'string', 'maxLength' => 60]]]],
                        'url' => ['type' => ['string', 'null'], 'maxLength' => 200],
                    ]],
                    'serve' => ['type' => ['string', 'null'], 'enum' => ['home', 'away', null], 'description' => 'Equipe que saca (esportes de sets); aparece como bola ao lado do placar. Zera ao trocar o jogo.'],
                    'mvp_person_id' => ['type' => ['string', 'null'], 'description' => 'Craque do jogo (atleta da escalação) mostrado no resumo; vale só para o jogo no ar.'],
                ]],
                'custom_live' => ['type' => 'object', 'description' => 'Mostra (true) ou oculta (false) ao vivo os overlays livres (Builder) da organização, por ID. IDs que não existem são ignorados.', 'additionalProperties' => ['type' => 'boolean']],
                'championship_live' => ['type' => 'object', 'description' => 'Mostra (true) ou oculta (false) ao vivo as telas de campeonato abertas com `live=1` na URL. Chaves: standings, fixtures, results, scorers, discipline, matchcard, ticker, champion.', 'additionalProperties' => ['type' => 'boolean']],
                'appearance' => ['type' => 'object', 'properties' => [
                    'preset' => ['type' => 'string', 'enum' => ['arena', 'classic', 'minimal', 'glass', 'contrast', 'neon', 'ribbon', 'gradient', 'aurora']],
                    'accent_color' => ['type' => 'string'],
                    'background_color' => ['type' => 'string'],
                    'text_color' => ['type' => 'string'],
                    'position' => ['type' => 'string', 'enum' => ['top-left', 'top-center', 'top-right', 'bottom-left', 'bottom-center', 'bottom-right']],
                    'scale' => ['type' => 'number'],
                    'scorers' => ['type' => 'boolean', 'description' => 'Mostra os autores dos gols sob o placar (esportes de gols).'],
                    'layout' => ['type' => 'string', 'enum' => ['bar', 'stacked', 'compact', 'wide'], 'description' => 'Formato do placar: barra, equipes empilhadas, pílula compacta ou faixa de ponta a ponta.'],
                    'motion' => ['type' => 'string', 'enum' => ['dynamic', 'subtle', 'none']],
                    'font' => ['type' => 'string', 'description' => 'Fonte do catálogo (system, inter, montserrat, oswald, bebas, anton, barlow, roboto-condensed, archivo, playfair, marker) ou ID de uma fonte própria da organização.'],
                    'x' => ['type' => ['integer', 'null'], 'description' => 'Posição horizontal livre em % da tela (0 a 100). Vale só se `y` também vier; nulo volta a usar `position`.'],
                    'y' => ['type' => ['integer', 'null'], 'description' => 'Posição vertical livre em % da tela (0 a 100).'],
                    'text_scale' => ['type' => 'integer', 'description' => 'Tamanho só do texto do placar, 50 a 200 (%).'],
                    'show_badge' => ['type' => 'boolean', 'description' => 'Mostra os escudos no placar.'],
                    'radius' => ['type' => ['integer', 'null'], 'description' => 'Raio dos cantos em px (0 a 40); nulo = automático pelo formato.'],
                    'surface' => ['type' => 'integer', 'description' => 'Opacidade do fundo, 30 a 100 (%).'],
                    'accent_size' => ['type' => 'integer', 'description' => 'Espessura da faixa na cor da equipe, 0 a 8 px.'],
                    'shadow' => ['type' => 'string', 'enum' => ['none', 'soft', 'strong']],
                    'entrance' => ['type' => 'string', 'enum' => ['none', 'fade', 'slide', 'zoom', 'assemble'], 'description' => 'Animação de entrada do placar ao ligar a camada.'],
                    'entrance_speed' => ['type' => 'integer', 'description' => 'Velocidade da entrada, 50 a 200 (%).'],
                    'period_scale' => ['type' => 'integer', 'description' => 'Tamanho do bloco do relógio/período, 60 a 200 (%).'],
                    'extra_time_scale' => ['type' => 'integer', 'description' => 'Tamanho do indicador de acréscimos (+N), 60 a 200 (%).'],
                    'goal_celebration' => ['type' => 'boolean', 'description' => 'Comemora gols sobre o placar (esportes de gols).'],
                    'goal_text' => ['type' => 'string', 'description' => 'Palavra da comemoração (até 20 caracteres).'],
                    'goal_word_seconds' => ['type' => 'integer', 'description' => 'Segundos da palavra, 1 a 6.'],
                    'goal_team_seconds' => ['type' => 'integer', 'description' => 'Segundos do nome da equipe depois da palavra, 0 a 6 (0 = não mostra).'],
                    'goal_animation' => ['type' => 'string', 'enum' => ['typewriter', 'bounce', 'sweep']],
                ]],
                'stats_settings' => ['type' => 'object', 'description' => 'Estatísticas da partida no ar (tela `estatisticas`). Os números valem para o jogo no ar; trocar de jogo zera. Para ligar a tela use `screens_live.stats`.', 'properties' => [
                    'home' => ['type' => 'object', 'description' => 'Números do mandante (0 a 999): shots, shots_on_target, corners, fouls, offsides, saves. Mescla com o que já estava salvo.', 'additionalProperties' => ['type' => 'integer']],
                    'away' => ['type' => 'object', 'description' => 'Números do visitante (mesmas chaves).', 'additionalProperties' => ['type' => 'integer']],
                    'possession' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100, 'description' => 'Posse de bola do mandante; a do visitante é 100 menos este valor.'],
                    'view' => ['type' => 'string', 'enum' => ['comparative', 'events', 'player']],
                    'metrics' => ['type' => 'array', 'maxItems' => 6, 'items' => ['type' => 'string', 'enum' => ['possession', 'shots', 'shots_on_target', 'corners', 'fouls', 'offsides', 'saves']]],
                    'player' => ['type' => ['string', 'null'], 'description' => 'Atleta em destaque (da escalação do jogo); a foto só aparece se ele estiver habilitado na escalação.'],
                    'note' => ['type' => 'string', 'maxLength' => 120],
                    'style' => ['type' => 'string', 'enum' => ['broadcast', 'glass', 'minimal']],
                    'animation' => ['type' => 'string', 'enum' => ['rise', 'slide', 'zoom', 'wipe', 'fade']],
                    'duration' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 120, 'description' => 'Segundos no ar ao mostrar a tela; 0 = até ocultar.'],
                    'scale' => ['type' => 'integer', 'minimum' => 50, 'maximum' => 200],
                    'text_scale' => ['type' => 'integer', 'minimum' => 50, 'maximum' => 200],
                    'x' => ['type' => ['integer', 'null'], 'description' => 'Posição livre horizontal em %; só vale com `y`.'],
                    'y' => ['type' => ['integer', 'null']],
                ]],
                'sponsor_settings' => ['type' => 'object', 'properties' => [
                    'enabled' => ['type' => 'boolean'],
                    'seconds' => ['type' => 'integer', 'minimum' => 3, 'maximum' => 30],
                    'format' => ['type' => 'string', 'enum' => ['logo', 'logo_name', 'text', 'banner', 'banner_name'], 'description' => 'O que a rotação mostra de cada patrocinador. Os formatos de banner caem no logotipo se o patrocinador não enviou banner.'],
                    'style' => ['type' => 'string', 'enum' => ['boxed', 'clean', 'ribbon']],
                    'animation' => ['type' => 'string', 'enum' => ['fade', 'slide', 'zoom', 'rise', 'flip']],
                    'animation_speed' => ['type' => 'integer', 'minimum' => 50, 'maximum' => 200],
                    'scale' => ['type' => 'integer', 'minimum' => 50, 'maximum' => 200],
                    'text_scale' => ['type' => 'integer', 'minimum' => 50, 'maximum' => 200],
                    'x' => ['type' => ['integer', 'null'], 'description' => 'Posição livre horizontal em % da tela; só vale com `y`.'],
                    'y' => ['type' => ['integer', 'null']],
                    'bar_seconds' => ['type' => 'integer', 'minimum' => 3, 'maximum' => 60, 'description' => 'Barra 1500 x 200: segundos de cada banner.'],
                    'bar_transition' => ['type' => 'string', 'enum' => ['fade', 'slide', 'zoom', 'flip', 'elastic']],
                    'bar_fit' => ['type' => 'string', 'enum' => ['cover', 'contain']],
                    'bar_border' => ['type' => 'string', 'enum' => ['none', 'thin', 'accent']],
                    'bar_shadow' => ['type' => 'string', 'enum' => ['none', 'soft', 'strong']],
                    'bar_scale' => ['type' => 'integer', 'minimum' => 50, 'maximum' => 150],
                    'bar_x' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                    'bar_y' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                    'bar_opacity' => ['type' => 'integer', 'minimum' => 30, 'maximum' => 100],
                    'bar_radius' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 40],
                    'bar_background' => ['type' => 'string', 'description' => 'Cor de fundo da barra (#rrggbb).'],
                ]],
                'lineup_settings' => ['type' => 'object', 'properties' => [
                    'presentation' => ['type' => 'string', 'enum' => ['sequence', 'list', 'formation', 'cards', 'individual', 'show', 'full'], 'description' => '`cards` = grade de cartões; `individual` = um jogador por vez; `show` = grade e depois cada jogador; `full` = grade, esquema tático da equipe e cada jogador, com a faixa de patrocinadores fixa por baixo.'],
                    'formation_seconds' => ['type' => 'integer', 'minimum' => 3, 'maximum' => 30, 'description' => 'Tempo do esquema tático na apresentação `full`.'],
                    'full_individuals' => ['type' => 'boolean', 'description' => 'Na apresentação `full`, passa também jogador a jogador depois do esquema.'],
                    'transition' => ['type' => 'string', 'enum' => ['fade', 'slide', 'rise', 'wipe', 'curtain', 'split', 'zoom', 'flip'], 'description' => 'Animação de troca entre as etapas da apresentação por equipe.'],
                    'package_animation' => ['type' => 'string', 'enum' => ['none', 'fade', 'slide', 'rise', 'wipe', 'curtain', 'split', 'zoom', 'flip'], 'description' => 'Entrada e saída do pacote inteiro da escalação (cartões, esquema, jogadores e faixa de patrocinadores) ao ligar e desligar a camada.'],
                    'motion_speed' => ['type' => 'integer', 'minimum' => 50, 'maximum' => 200, 'description' => 'Velocidade das animações da escalação em % (100 = normal).'],
                    'list_seconds' => ['type' => 'integer'],
                    'photo_person_ids' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'team' => ['type' => 'string', 'enum' => ['home', 'away'], 'description' => 'Equipe apresentada em `cards`, `individual` e `show`.'],
                    'individual_mode' => ['type' => 'string', 'enum' => ['auto', 'manual'], 'description' => 'Automático = troca de jogador a cada `individual_seconds`; manual = avança com `lineup_command`.'],
                    'individual_seconds' => ['type' => 'integer', 'minimum' => 3, 'maximum' => 30],
                    'individual_scope' => ['type' => 'string', 'enum' => ['starters', 'all']],
                    'sponsor_banner' => ['type' => 'boolean', 'description' => 'Mostra a faixa de patrocinadores nos cartões.'],
                    'layout' => ['type' => 'string', 'enum' => ['classic', 'tunnel', 'poster', 'sidebar', 'stadium', 'matchday'], 'description' => 'Conceito visual das apresentações por equipe (cartões e jogador a jogador).'],
                    'style' => ['type' => 'string', 'enum' => ['editorial', 'cards', 'glass', 'premier', 'champions', 'diagonal'], 'description' => 'Estilo do conceito `classic`.'],
                    'animation' => ['type' => 'string', 'enum' => ['slide', 'wipe', 'rise', 'zoom', 'split', 'cascade'], 'description' => 'Animação de entrada da apresentação.'],
                    'formation_mark' => ['type' => 'string', 'enum' => ['photo', 'number'], 'description' => 'No esquema tático: foto do atleta (quando habilitada) ou sempre o número da camisa.'],
                    'formation_photo_size' => ['type' => 'integer', 'minimum' => 60, 'maximum' => 200, 'description' => 'Tamanho da foto/número no esquema tático (%).'],
                    'sponsor_size' => ['type' => 'integer', 'minimum' => 60, 'maximum' => 180, 'description' => 'Tamanho da faixa de patrocinadores (%).'],
                    'x' => ['type' => ['integer', 'null'], 'description' => 'Posição livre horizontal em % da tela; só vale com `y`.'],
                    'y' => ['type' => ['integer', 'null'], 'description' => 'Posição livre vertical em % da tela.'],
                    'surface' => ['type' => 'integer', 'minimum' => 40, 'maximum' => 100, 'description' => 'Opacidade do fundo (%).'],
                    'radius' => ['type' => ['integer', 'null'], 'description' => 'Raio dos cantos em px (0 a 40); nulo = automático.'],
                    'text_scale' => ['type' => 'integer', 'minimum' => 50, 'maximum' => 200, 'description' => 'Tamanho do texto (%).'],
                ]],
                'lineup_command' => ['type' => 'string', 'enum' => ['next', 'previous', 'reset'], 'description' => 'Passa a apresentação da escalação para a etapa seguinte/anterior ou a reinicia (o servidor calcula a etapa).'],
            ]],
            'example' => [
                'game_id' => '01JABCDEF0123456789XYZ0001',
                'layers' => ['scoreboard' => true, 'lower_third' => false, 'lineup' => true, 'standings' => false],
                'appearance' => ['preset' => 'arena', 'accent_color' => '#22c55e', 'background_color' => '#07111f', 'text_color' => '#ffffff', 'position' => 'top-center', 'scale' => 1, 'motion' => 'dynamic', 'font' => 'oswald'],
                'sponsor_settings' => ['enabled' => true, 'seconds' => 8],
                'lineup_settings' => ['presentation' => 'sequence', 'list_seconds' => 8, 'photo_person_ids' => []],
            ],
        ],
        'responses' => [
            '200' => [
                'description' => 'Estado completo e atualizado do canal.',
                'schema' => ['type' => 'object', 'required' => ['data'], 'properties' => ['data' => ['type' => 'object']]],
                'example' => ['data' => ['channel' => 'Transmissão principal', 'layers' => ['scoreboard' => true, 'lower_third' => false, 'lineup' => true, 'standings' => false], 'game' => null, 'lineup' => null, 'standings' => null, 'server_time_ms' => 1792000000000]],
            ],
            '401' => ['description' => 'Chave de controle ausente ou inválida.'],
            '404' => ['description' => 'Canal inexistente ou recurso `overlays` indisponível.'],
            '422' => ['description' => 'Estado, aparência ou configuração da escalação inválidos.'],
            '429' => $throttle,
        ],
    ],

    // ───────────── Tempo real e avisos ─────────────
    'public.games.state' => [
        'summary' => 'Estado do jogo ao vivo (contingência por consulta periódica)',
        'description' => 'A página `/jogos/{game}` recebe as atualizações pelo Reverb (canal público `games.{id}`, evento `game.updated`, mesmo conteúdo). Se o WebSocket cair, a página consulta esta rota de tempos em tempos. Sem envelope `data`.',
        'parameters' => ['game' => 'ID (ULID) de um jogo de edição publicada.'],
        'responses' => [
            '200' => ['description' => 'O retrato público do jogo.', 'schema' => $snapshotSchema, 'example' => $snapshotExample],
            '404' => $notFound,
        ],
    ],
    'overlay.state' => [
        'summary' => 'Estado completo de um canal de overlay (contingência do OBS)',
        'description' => 'O token do canal na URL é o acesso. O estado inclui o jogo no ar e, só se o operador ligou a camada, a escalação e a tabela; cartões, disciplina e documentos nunca vêm. Com `?pvw=chave` (lineup, standings, sponsors ou uma tela extra) devolve o estado com aquela camada ligada só em memória, para o PVW do Modo operador; isso exige sessão do painel (403 sem ela) e nada é gravado. Exige o recurso `overlays`.',
        'parameters' => ['channel' => 'Token secreto do canal de overlay.'],
        'responses' => [
            '200' => [
                'description' => 'Estado que o cliente do overlay apenas substitui. `game` é o mesmo retrato público do jogo; `lineup` e `standings` só vêm com a camada ligada.',
                'schema' => ['type' => 'object', 'required' => ['channel', 'layers', 'game', 'lineup', 'standings', 'server_time_ms'], 'properties' => [
                    'channel' => ['type' => 'string'],
                    'layers' => ['type' => 'object', 'description' => 'Camadas sob demanda (lineup, standings) e se estão ligadas.'],
                    'game' => ['type' => ['object', 'null']],
                    'lineup' => ['type' => ['object', 'null']],
                    'standings' => ['type' => ['object', 'null']],
                    'server_time_ms' => ['type' => 'integer'],
                ]],
                'example' => ['channel' => 'Transmissão principal', 'layers' => ['lineup' => false, 'standings' => false], 'game' => null, 'lineup' => null, 'standings' => null, 'server_time_ms' => 1792000000000],
            ],
            '404' => ['description' => 'Token inexistente ou organização sem o recurso `overlays`.'],
        ],
    ],
    'overlay.show' => [
        'summary' => 'Tela de overlay para o OBS (placar, eventos, escalacao, tabela, telas extras, todos)',
        'description' => 'Página HTML sem login: o token do canal na URL é o acesso. O nome da tela vai em `{overlay}` (a lista vem do painel de Transmissão). `pgm` é a visão do Modo operador (todas as telas empilhadas, uma só conexão) e não é fonte do OBS; com `?pvw=chave` vira o PVW (a camada ou tela escolhida ligada só em memória) e EXIGE sessão do painel, senão responde 403. Exige o recurso `overlays`.',
        'parameters' => ['channel' => 'Token secreto do canal de overlay.', 'overlay' => 'Nome da tela (placar, eventos, escalacao, tabela, patrocinadores, prejogo, comparativo, cartela, resumo, chaveamento, social, barra-patrocinio, estatisticas, penaltis, todos ou pgm).'],
        'responses' => [
            '200' => ['description' => 'Página HTML (Inertia) que carrega o estado inicial e passa a receber atualizações pelo Reverb, com consulta periódica de contingência.'],
            '403' => ['description' => '`?pvw` sem sessão do painel.'],
            '404' => ['description' => 'Token inexistente, tela desconhecida ou organização sem o recurso `overlays`.'],
        ],
    ],
    'overlay.font' => [
        'summary' => 'Arquivo da fonte própria usada pelo canal',
        'description' => 'Serve o arquivo (woff2, woff, ttf ou otf, `nosniff`, cache de 1 dia) pela mesma origem do overlay. Só existe para fontes cadastradas pela organização do canal.',
        'parameters' => ['channel' => 'Token secreto do canal de overlay.', 'font' => 'ID (ULID) da fonte da organização.'],
        'responses' => [
            '200' => ['description' => 'Bytes da fonte, com o `Content-Type` do formato.'],
            '404' => ['description' => 'Fonte inexistente ou de outra organização.'],
        ],
    ],
    'overlay.custom' => [
        'summary' => 'Overlay livre (Builder) de um canal',
        'description' => 'Página HTML com o desenho já saneado (cores, números e listas validados; imagens só `https` ou tokens resolvidos). Só aparece no ar quando o operador liga o item no canal (`custom_live`).',
        'parameters' => ['channel' => 'Token secreto do canal de overlay.', 'item' => 'ID (ULID) do overlay livre.'],
        'responses' => [
            '200' => ['description' => 'Página HTML (Inertia) do overlay livre.'],
            '404' => ['description' => 'Token inexistente, item de outra organização ou organização sem o recurso `overlays`.'],
        ],
    ],
    'overlay.championship.data' => [
        'summary' => 'Dados das telas de campeonato do overlay',
        'description' => 'JSON consumido pelas telas `/overlay/{token}/campeonato/{tela}`: a edição publicada (por `?edicao=`, `?c=`/`?div=` ou o jogo no ar), com classificação, jogos, artilharia e campeão. Cartões só com o interruptor `game_cards` do portal; nunca IDs de atletas.',
        'parameters' => ['channel' => 'Token secreto do canal de overlay.'],
        'responses' => [
            '200' => ['description' => 'Pacote de dados da edição para a tela.'],
            '404' => ['description' => 'Token inexistente, edição em rascunho ou organização sem o recurso `overlays`.'],
        ],
    ],
    'overlay.championship' => [
        'summary' => 'Tela de campeonato para o OBS (classificação, jogos, artilharia, cartões, destaque, faixa, campeão)',
        'description' => 'Página HTML cuja aparência vem toda da URL. Com `live=1` só aparece quando o operador mostra a tela no painel.',
        'parameters' => ['channel' => 'Token secreto do canal de overlay.', 'view' => 'standings, fixtures, results, scorers, discipline, matchcard, ticker ou champion.'],
        'responses' => [
            '200' => ['description' => 'Página HTML (Inertia).'],
            '404' => ['description' => 'Token inexistente, tela desconhecida ou organização sem o recurso `overlays`.'],
        ],
    ],
    'embed.game' => [
        'summary' => 'Widget: placar ao vivo de um jogo (para iframe)',
        'description' => 'Página sem login para incorporar em outros sites; só jogos de edição publicada. Aceita `?tema=escuro` e `?cor=%23rrggbb`. Envia a altura ao site pai por `postMessage`. Única família de rotas liberada para iframe de qualquer origem. Exige o recurso `embeds`.',
        'parameters' => ['game' => 'ID (ULID) de um jogo de edição publicada.'],
        'responses' => [
            '200' => ['description' => 'Página HTML do widget (atualiza pelo Reverb, com consulta de contingência).'],
            '404' => $notFound,
        ],
    ],
    'embed.standings' => [
        'summary' => 'Widget: classificação de uma divisão (para iframe)',
        'description' => 'Aceita `?linhas=` (quantidade de linhas), `?tema=escuro` e `?cor=%23rrggbb`. Só edição publicada. Exige o recurso `embeds`.',
        'parameters' => ['division' => 'ID (ULID) de uma divisão de edição publicada.'],
        'responses' => [
            '200' => ['description' => 'Página HTML do widget.'],
            '404' => $notFound,
        ],
    ],
    'embed.games' => [
        'summary' => 'Widget: jogos de uma edição (para iframe)',
        'description' => 'Próximos jogos e resultados recentes de uma edição publicada. Aceita `?tema=escuro` e `?cor=%23rrggbb`. Exige o recurso `embeds`.',
        'parameters' => ['season' => 'ID (ULID) de uma edição publicada.'],
        'responses' => [
            '200' => ['description' => 'Página HTML do widget.'],
            '404' => $notFound,
        ],
    ],
    'public.games.show' => [
        'summary' => 'Página pública do jogo (placar ao vivo)',
        'description' => 'Página HTML renderizada no servidor (SEO, Open Graph e dados estruturados), com linha do tempo, tabela, forma e confronto direto conforme os interruptores do portal. Atualiza pelo Reverb.',
        'parameters' => ['game' => 'ID (ULID) de um jogo de edição publicada.'],
        'responses' => [
            '200' => ['description' => 'Página HTML (Inertia, com SSR).'],
            '404' => $notFound,
        ],
    ],
    'public.games.image' => [
        'summary' => 'Imagem de compartilhamento do jogo (PNG 1200x630)',
        'description' => 'Gerada com GD a partir do retrato público (equipes, escudos, placar, competição, data e local); nunca traz atletas nem cartões. Cache de 60 s. Alimenta `og:image` e `twitter:card`.',
        'parameters' => ['game' => 'ID (ULID) de um jogo de edição publicada.'],
        'responses' => [
            '200' => ['description' => 'Imagem `image/png`.'],
            '404' => $notFound,
        ],
    ],
    'push.subscribe' => [
        'summary' => 'Pede aviso push de um jogo ou de uma equipe',
        'description' => 'Sem login. A inscrição é anônima (endpoint do navegador e chaves; sem nome, e-mail ou IP). Só jogos de edição publicada e equipes com inscrição confirmada em edição publicada. Exige o recurso `push` e as chaves VAPID.',
        'request' => [
            'schema' => ['type' => 'object', 'required' => ['kind', 'id', 'endpoint', 'keys'], 'properties' => [
                'kind' => ['type' => 'string', 'enum' => ['game', 'team']],
                'id' => ['type' => 'string', 'description' => 'ULID do jogo ou da equipe (26 caracteres).'],
                'endpoint' => ['type' => 'string', 'description' => 'URL HTTPS do serviço de push do navegador.'],
                'keys' => ['type' => 'object', 'required' => ['p256dh', 'auth'], 'properties' => ['p256dh' => ['type' => 'string'], 'auth' => ['type' => 'string']]],
            ]],
            'example' => ['kind' => 'game', 'id' => '01JABCDEF0123456789XYZ0001', 'endpoint' => 'https://push.exemplo.test/send/abc', 'keys' => ['p256dh' => 'BPublicKey...', 'auth' => 'authSecret...']],
        ],
        'responses' => [
            '201' => ['description' => 'Inscrição criada ou atualizada.', 'schema' => ['type' => 'object', 'required' => ['subscribed'], 'properties' => ['subscribed' => ['type' => 'boolean']]], 'example' => ['subscribed' => true]],
            '404' => ['description' => 'Jogo ou equipe fora do público (edição em rascunho), ou recurso `push` desligado.'],
            '422' => ['description' => 'Dados inválidos.'],
            '429' => $throttle,
        ],
    ],
    'push.unsubscribe' => [
        'summary' => 'Cancela o aviso push de um jogo ou de uma equipe',
        'request' => [
            'schema' => ['type' => 'object', 'required' => ['kind', 'id', 'endpoint'], 'properties' => [
                'kind' => ['type' => 'string', 'enum' => ['game', 'team']],
                'id' => ['type' => 'string'],
                'endpoint' => ['type' => 'string'],
            ]],
            'example' => ['kind' => 'game', 'id' => '01JABCDEF0123456789XYZ0001', 'endpoint' => 'https://push.exemplo.test/send/abc'],
        ],
        'responses' => [
            '200' => ['description' => 'Inscrição removida (ou inexistente).', 'schema' => ['type' => 'object', 'required' => ['subscribed'], 'properties' => ['subscribed' => ['type' => 'boolean']]], 'example' => ['subscribed' => false]],
            '422' => ['description' => 'Dados inválidos.'],
            '429' => $throttle,
        ],
    ],

    // ───────────── Painel ─────────────
    'panel.games.sync' => [
        'summary' => 'Envia em lote os lançamentos da fila offline da súmula',
        'description' => 'Aplica os itens EM ORDEM, pelas mesmas ações da súmula. Cada item tem uma chave própria do aparelho: chave já aplicada volta como `duplicate` (reenvio seguro). O primeiro item recusado interrompe o lote: ele volta como `rejected` (com o motivo) e os seguintes como `skipped`. A hora do lance (`at`, em milissegundos) é a do aparelho, alinhada ao relógio do servidor; hora futura vira agora. Exige a permissão de operar jogos. Dois envios simultâneos do mesmo jogo: o segundo recebe 423.',
        'parameters' => ['game' => 'ID (ULID) do jogo.'],
        'request' => [
            'schema' => ['type' => 'object', 'required' => ['items'], 'properties' => ['items' => ['type' => 'array', 'items' => [
                'type' => 'object',
                'required' => ['key', 'kind', 'at', 'payload'],
                'properties' => [
                    'key' => ['type' => 'string', 'description' => 'Chave única do item (UUID gerado no aparelho), até 64 caracteres.'],
                    'kind' => ['type' => 'string', 'enum' => ['event', 'point', 'period', 'clock', 'void', 'finish']],
                    'at' => ['type' => 'integer', 'description' => 'Hora do lance, em milissegundos desde 1970.'],
                    'payload' => ['type' => 'object', 'description' => 'Dados do lançamento, conforme o `kind` (ex.: type, entry_id e person_id em `event`; start e period em `period`).'],
                ],
            ]]]],
            'example' => ['items' => [
                ['key' => '7d9f6f0e-2b0a-4c3a-9c55-0f1b2c3d4e5f', 'kind' => 'period', 'at' => 1792000000000, 'payload' => ['start' => true, 'period' => 1]],
                ['key' => '0a1b2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d', 'kind' => 'event', 'at' => 1792000060000, 'payload' => ['type' => 'goal', 'entry_id' => '01JABCDEF0123456789XYZ0040', 'person_id' => '01JABCDEF0123456789XYZ0050']],
            ]],
        ],
        'responses' => [
            '200' => [
                'description' => 'Resultado de cada item, na mesma ordem.',
                'schema' => ['type' => 'object', 'required' => ['results'], 'properties' => ['results' => ['type' => 'array', 'items' => [
                    'type' => 'object',
                    'required' => ['key', 'status', 'message'],
                    'properties' => [
                        'key' => ['type' => 'string'],
                        'status' => ['type' => 'string', 'enum' => ['applied', 'duplicate', 'rejected', 'skipped']],
                        'message' => ['type' => ['string', 'null'], 'description' => 'Motivo, quando `rejected`.'],
                    ],
                ]]]],
                'example' => ['results' => [['key' => '7d9f6f0e-2b0a-4c3a-9c55-0f1b2c3d4e5f', 'status' => 'applied', 'message' => null], ['key' => '0a1b2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d', 'status' => 'rejected', 'message' => 'Jogador expulso não pode marcar.']]],
            ],
            '403' => ['description' => 'Sem a permissão de operar jogos.'],
            '422' => ['description' => 'Lote inválido (1 a 200 itens).'],
            '423' => ['description' => 'Outro envio do mesmo jogo está em andamento; tente de novo.'],
        ],
    ],
    'panel.sheet-import.template' => [
        'summary' => 'Baixa o modelo CSV da súmula digital',
        'description' => 'Colunas `tipo;equipe;nome;camisa;minuto;valor`, separador `;`, UTF-8 com BOM. Exige o recurso `sheet_import`.',
        'responses' => ['200' => ['description' => 'Arquivo `modelo-sumula.csv`.']],
    ],

    // ───────────── Plataforma ─────────────
    'platform.api-docs.openapi' => [
        'summary' => 'Baixa esta referência em OpenAPI 3.1 (JSON)',
        'description' => 'Para Postman, Insomnia ou qualquer ferramenta OpenAPI. Documento interno: só o Super Admin acessa.',
        'responses' => ['200' => ['description' => 'Especificação OpenAPI 3.1.']],
    ],
];
