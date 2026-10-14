<?php

return [

    /*
    | Organização usada enquanto a multi-tenancy completa (subdomínios, papéis) não existe.
    | Toda tabela de domínio já carrega organization_id para permitir a evolução sem retrabalho.
    */
    'default_organization_slug' => env('DEFAULT_ORGANIZATION_SLUG', 'default'),

    /*
    | Domínio base dos subdomínios das organizações: `{slug}.{base}` abre a organização de mesmo slug.
    | Vazio = sem subdomínios (só a organização padrão e domínios próprios cadastrados).
    */
    'base_domain' => env('PLATFORM_BASE_DOMAIN'),

    /*
    | Chave do hash do CPF (LGPD: o CPF não é guardado em claro). Se vazia, usa a APP_KEY.
    | Mantenha estável: trocar a chave invalida a busca por CPF dos cadastros existentes.
    */
    'document_hash_key' => env('DOCUMENT_HASH_KEY'),

    /*
    | Cadastro público de usuários. Fechado por padrão: um administrador cria os acessos. Uma instalação sem
    | nenhum usuário aceita o primeiro cadastro mesmo assim (ele vira administrador).
    */
    'open_registration' => (bool) env('OPEN_REGISTRATION', false),

    /*
    | Endereço PÚBLICO do Reverb, usado pelo navegador (o servidor Laravel fala com o container "reverb" pelas
    | variáveis REVERB_*). Lido em tempo de execução, então não exige rebuild do front. Sem host, usa o do site.
    */
    'realtime' => [
        'key' => env('REVERB_APP_KEY'),
        'host' => env('REVERB_PUBLIC_HOST'),
        'port' => (int) env('REVERB_PUBLIC_PORT', 8081),
        'scheme' => env('REVERB_PUBLIC_SCHEME', 'http'),
    ],

    /*
    | Status page (docs/14): limites das sondagens. `fail_threshold` falhas seguidas abrem um incidente automático e
    | `recover_threshold` sucessos seguidos o resolvem. `publish_disk` (opcional) recebe o retrato estático `status/status.json`
    | a cada sondagem (contingência quando a aplicação cai); `storage_disk` é o disco consultado pela sonda de arquivos.
    */
    'status' => [
        'fail_threshold' => (int) env('STATUS_FAIL_THRESHOLD', 3),
        'recover_threshold' => (int) env('STATUS_RECOVER_THRESHOLD', 2),
        'retention_days' => 90,
        'queue_max_age' => (int) env('STATUS_QUEUE_MAX_AGE', 180),
        'publish_disk' => env('STATUS_PUBLISH_DISK'),
        'storage_disk' => env('STATUS_STORAGE_DISK', 's3'),
    ],

    /*
    | Leitura de súmulas enviadas (ADR 0004). Limites de custo da leitura por IA (a estruturada CSV/XLSX é gratuita):
    | teto de leituras por jogo (a organização pode baixar) e leituras por organização por mês.
    */
    'sheet_import' => [
        'max_reads_per_game' => (int) env('SHEET_IMPORT_MAX_READS_PER_GAME', 3),
        'max_reads_per_game_ceiling' => 10,
        'monthly_reads' => (int) env('SHEET_IMPORT_MONTHLY_READS', 200),
    ],

    /*
    | Mercado de jogadores livres (ADR 0008): pedidos de contato por equipe por dia (a organização pode mudar em
    | `settings.market.daily_requests`) e dias de espera até o pedido expirar.
    */
    'market' => [
        'daily_requests' => (int) env('MARKET_DAILY_REQUESTS', 10),
        'request_days' => (int) env('MARKET_REQUEST_DAYS', 14),
    ],

];
