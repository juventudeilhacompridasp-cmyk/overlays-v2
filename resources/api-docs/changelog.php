<?php

/*
| Histórico de mudanças da referência das APIs (ADR 0007). Mais recente primeiro; a primeira versão é a corrente
| (`info.version` da especificação). Versão semântica: maior = quebra de contrato público (`/api/v1`), menor = rota ou campo
| novo, correção = texto/exemplo. Cada entrada: version, date (AAAA-MM-DD) e changes (frases curtas em português).
*/

return [
    [
        'version' => '1.14.0',
        'date' => '2026-10-26',
        'changes' => ['Novas rotas internas panel.activity-log.index e panel.activity-log.export: registro de atividades (auditoria) de tudo o que os usuários fazem.'],
    ],
    [
        'version' => '1.13.0',
        'date' => '2026-10-26',
        'changes' => ['Novas rotas internas platform.accounts.index e platform.accounts.reset: o Super Admin reinicia os dois fatores de uma conta. A ativação e a desativação dos dois fatores passam a avisar a pessoa por e-mail.'],
    ],
    [
        'version' => '1.12.0',
        'date' => '2026-10-26',
        'changes' => ['Nova rota interna panel.broadcast.remote: controle remoto da transmissão para celular e tablet. Os canais do painel passam a trazer o campo stoppage (acréscimos).'],
    ],
    [
        'version' => '1.11.0',
        'date' => '2026-10-26',
        'changes' => ['Nova rota interna panel.document-access.index: registro de quem abriu documentos pessoais. As rotas que abrem documentos (panel.registrations.documents.show, panel.people.documents.show, panel.games.uploads.show) passam a gravar o acesso.'],
    ],
    [
        'version' => '1.10.0',
        'date' => '2026-10-26',
        'changes' => ['Novas rotas de autenticação em dois fatores (TOTP): two-factor.challenge e two-factor.verify no login e profile.two-factor.* em Meu perfil. O login (POST /login) passa a redirecionar ao código quando a conta tem dois fatores.'],
    ],
    [
        'version' => '1.9.0',
        'date' => '2026-10-26',
        'changes' => ['Novas rotas internas panel.people.documents.* e panel.documents.index: documentos com validade das pessoas (atestado médico) e lista de vencimentos. Regulamento da edição ganha documents.medical_required e documents.warn_days.'],
    ],
    [
        'version' => '1.8.0',
        'date' => '2026-10-26',
        'changes' => ['Nova rota interna panel.games.reports.sheet: súmula oficial do jogo em PDF, pronta para imprimir e assinar.'],
    ],
    [
        'version' => '1.7.0',
        'date' => '2026-10-26',
        'changes' => ['Novas rotas internas panel.officials.* (cadastro de árbitros e mesários), panel.stages.officials e panel.games.officials.update (escala dos jogos com aviso de conflito e e-mail).'],
    ],
    [
        'version' => '1.6.1',
        'date' => '2026-10-26',
        'changes' => ['panel.stages.draw.store aceita partial no mata-mata: só parte das equipes confirmadas entra na chave (ex.: classificados da fase anterior).'],
    ],
    [
        'version' => '1.6.0',
        'date' => '2026-10-26',
        'changes' => ['Nova rota interna panel.stages.schedule: agenda da fase por dia, com aviso de conflito de estádio e de descanso das equipes.'],
    ],
    [
        'version' => '1.5.0',
        'date' => '2026-10-26',
        'changes' => ['Novas rotas internas panel.stages.draw e panel.stages.draw.store: sorteio e chaves da fase arrastando equipes (grupos e mata-mata).'],
    ],
    [
        'version' => '1.4.0',
        'date' => '2026-10-26',
        'changes' => ['Nova rota interna panel.teams.show: ficha da equipe (dados, inscrições, elenco e jogos).'],
    ],
    [
        'version' => '1.3.0',
        'date' => '2026-10-21',
        'changes' => ['Nova rota interna panel.stages.fixtures.import: importa uma tabela de jogos já definida (sem sorteio).'],
    ],
    [
        'version' => '1.2.4',
        'date' => '2026-10-20',
        'changes' => ['Nova rota interna `panel.broadcast.custom.assets.store`: envio de imagem própria para os overlays livres.'],
    ],
    [
        'version' => '1.2.3',
        'date' => '2026-10-15',
        'changes' => ['Rotas de site (portal público, status page, landing, inscrição online, cadastro do Mercado e autenticação/conta) agora têm descrição em `resources/api-docs/site.php`.'],
    ],
    [
        'version' => '1.2.2',
        'date' => '2026-10-15',
        'changes' => ['Rotas da área da plataforma (organizações, status page, contatos, medição e documentação) agora têm descrição.'],
    ],
    [
        'version' => '1.2.1',
        'date' => '2026-10-15',
        'changes' => [
            'Nova rota interna `help.ask` (painel e gestor): pergunta à central de ajuda com resposta de IA, sob o recurso `help_ai`.',
        ],
    ],
    [
        'version' => '1.2.0',
        'date' => '2026-10-15',
        'changes' => [
            'Rotas internas do painel, do gestor de equipe e do atleta passam a ter descrição (telas, ações e arquivos), em `resources/api-docs/internal.php`.',
            'Descrição dos parâmetros de caminho dos recursos do painel (pessoa, elenco, pedido de inscrição, estádio, cena, patrocinador e outros).',
        ],
    ],
    [
        'version' => '1.1.1',
        'date' => '2026-10-15',
        'changes' => [
            'A organização pode desligar a API pública em Painel > Parâmetros: com ela desligada, as rotas `/api/v1` de consulta respondem 404 naquele endereço.',
        ],
    ],
    [
        'version' => '1.1.0',
        'date' => '2026-10-15',
        'changes' => [
            'Exportação para o Postman (coleção v2.1) a partir da mesma especificação, com variável `baseUrl` e exemplos de corpo.',
            'Histórico de mudanças da referência exibido na tela e versão da especificação ligada a ele.',
            'Rotas internas do tempo real, do push e dos widgets passam a ter descrição.',
        ],
    ],
    [
        'version' => '1.0.0',
        'date' => '2026-10-13',
        'changes' => [
            'Primeira versão: gerador OpenAPI 3.1 a partir das rotas registradas, catálogo de textos, tela de consulta e teste de cobertura das rotas `/api/*`.',
        ],
    ],
];
