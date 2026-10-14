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
                ]],
                'lower_third_show' => ['type' => 'object', 'description' => 'Dispara uma faixa agora (não é guardada). `custom` = título/subtítulo livres; `player` = atleta da escalação (`person_id`); os demais tipos enviam uma amostra com o jogo no ar. Só sai com a tela de lances no ar.', 'properties' => [
                    'kind' => ['type' => 'string', 'enum' => ['custom', 'player', 'goal', 'own_goal', 'yellow_card', 'red_card', 'blue_card', 'substitution']],
                    'title' => ['type' => 'string', 'maxLength' => 60],
                    'subtitle' => ['type' => 'string', 'maxLength' => 80],
                    'person_id' => ['type' => 'string'],
                ]],
                'championship_live' => ['type' => 'object', 'description' => 'Mostra (true) ou oculta (false) ao vivo as telas de campeonato abertas com `live=1` na URL. Chaves: standings, fixtures, results, scorers, discipline, matchcard, ticker, champion.', 'additionalProperties' => ['type' => 'boolean']],
                'appearance' => ['type' => 'object', 'properties' => [
                    'preset' => ['type' => 'string', 'enum' => ['arena', 'classic', 'minimal']],
                    'accent_color' => ['type' => 'string'],
                    'background_color' => ['type' => 'string'],
                    'text_color' => ['type' => 'string'],
                    'position' => ['type' => 'string', 'enum' => ['top-left', 'top-center', 'top-right', 'bottom-left', 'bottom-center', 'bottom-right']],
                    'scale' => ['type' => 'number'],
                    'layout' => ['type' => 'string', 'enum' => ['bar', 'stacked', 'compact', 'wide'], 'description' => 'Formato do placar: barra, equipes empilhadas, pílula compacta ou faixa de ponta a ponta.'],
                    'motion' => ['type' => 'string', 'enum' => ['dynamic', 'subtle', 'none']],
                    'font' => ['type' => 'string', 'description' => 'Fonte do catálogo (system, inter, montserrat, oswald, bebas, anton, barlow, roboto-condensed, archivo, playfair, marker) ou ID de uma fonte própria da organização.'],
                ]],
                'sponsor_settings' => ['type' => 'object', 'properties' => [
                    'enabled' => ['type' => 'boolean'],
                    'seconds' => ['type' => 'integer', 'minimum' => 3, 'maximum' => 30],
                ]],
                'lineup_settings' => ['type' => 'object', 'properties' => [
                    'presentation' => ['type' => 'string', 'enum' => ['sequence', 'list', 'formation', 'cards', 'individual', 'show'], 'description' => '`cards` = grade de cartões; `individual` = um jogador por vez; `show` = grade e depois cada jogador.'],
                    'list_seconds' => ['type' => 'integer'],
                    'photo_person_ids' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'team' => ['type' => 'string', 'enum' => ['home', 'away'], 'description' => 'Equipe apresentada em `cards`, `individual` e `show`.'],
                    'individual_mode' => ['type' => 'string', 'enum' => ['auto', 'manual'], 'description' => 'Automático = troca de jogador a cada `individual_seconds`; manual = avança com `lineup_command`.'],
                    'individual_seconds' => ['type' => 'integer', 'minimum' => 3, 'maximum' => 30],
                    'individual_scope' => ['type' => 'string', 'enum' => ['starters', 'all']],
                    'sponsor_banner' => ['type' => 'boolean', 'description' => 'Mostra a faixa de patrocinadores nos cartões.'],
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
        'description' => 'O token do canal na URL é o acesso. O estado inclui o jogo no ar e, só se o operador ligou a camada, a escalação e a tabela; cartões, disciplina e documentos nunca vêm. Exige o recurso `overlays`.',
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
