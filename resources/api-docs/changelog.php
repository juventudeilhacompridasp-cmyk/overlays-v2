<?php

/*
| Histórico de mudanças da referência das APIs (ADR 0007). Mais recente primeiro; a primeira versão é a corrente
| (`info.version` da especificação). Versão semântica: maior = quebra de contrato público (`/api/v1`), menor = rota ou campo
| novo, correção = texto/exemplo. Cada entrada: version, date (AAAA-MM-DD) e changes (frases curtas em português).
*/

return [
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
