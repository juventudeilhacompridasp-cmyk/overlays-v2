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
    | O primeiro cadastro de uma instalação sem nenhum usuário vira administrador. Num site PÚBLICO isso é uma janela de
    | tomada de controle (quem chegar primeiro em /register), então em produção o padrão é desligado
    | (ALLOW_FIRST_USER_SETUP=false na stack) e o primeiro administrador nasce por `php artisan platform:create-admin`.
    */
    'first_user_setup' => (bool) env('ALLOW_FIRST_USER_SETUP', true),

    /*
    | Autenticação em dois fatores (TOTP). Cada pessoa liga a sua em Meu perfil. `required_for_admins` (padrão desligado)
    | obriga quem administra usuários da organização e os Super Admins a ativá-la antes de usar o painel/plataforma.
    */
    'two_factor' => [
        'required_for_admins' => (bool) env('PLATFORM_2FA_REQUIRED_FOR_ADMINS', false),
        // Parâmetros da validação (lidos por `App\Support\Auth\TwoFactorSettings`, que aplica os limites): quantos códigos de
        // recuperação gerar, por quantos minutos vale a verificação depois da senha, erros de código por minuto antes de
        // bloquear, tolerância do relógio em passos de 30 s e se a pessoa recebe e-mail ao ativar/desativar/reiniciar.
        'recovery_codes' => (int) env('PLATFORM_2FA_RECOVERY_CODES', 8),
        'challenge_minutes' => (int) env('PLATFORM_2FA_CHALLENGE_MINUTES', 10),
        'max_attempts' => (int) env('PLATFORM_2FA_MAX_ATTEMPTS', 5),
        'window' => (int) env('PLATFORM_2FA_WINDOW', 1),
        'notify' => (bool) env('PLATFORM_2FA_NOTIFY', true),
    ],

    /*
    | Registro de atividades (auditoria no banco, `activity_log`). `enabled` liga/desliga a gravação; `retention_days` é por quanto
    | tempo guardar (mínimo 30); `log_refused` também guarda as tentativas recusadas (sem permissão, validação, erro). Lido por
    | `App\Modules\Tenancy\Support\ActivitySettings`.
    */
    'activity_log' => [
        'enabled' => (bool) env('ACTIVITY_LOG_ENABLED', true),
        'retention_days' => (int) env('ACTIVITY_LOG_RETENTION_DAYS', 365),
        'log_refused' => (bool) env('ACTIVITY_LOG_REFUSED', true),
    ],

    /*
    | Segurança das respostas. `hsts_seconds` = Strict-Transport-Security (só em HTTPS; 0 desliga). Sem `includeSubDomains`
    | de propósito: subdomínios de organizações e domínios próprios podem ter ciclos de HTTPS diferentes.
    */
    'security' => [
        'hsts_seconds' => max(0, (int) env('HSTS_SECONDS', 15552000)),
    ],

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
    | Limites de segurança da plataforma (docs/10, item 5): requisições por minuto (por IP) de cada grupo de rotas públicas e
    | minutos de validade dos links assinados de arquivos privados. O padrão é o comportamento original; veja `App\Support\PlatformLimits`.
    */
    'limits' => [
        'api' => (int) env('PLATFORM_LIMIT_API', 60),
        'overlay' => (int) env('PLATFORM_LIMIT_OVERLAY', 120),
        'embed' => (int) env('PLATFORM_LIMIT_EMBED', 120),
        'push' => (int) env('PLATFORM_LIMIT_PUSH', 30),
        'card' => (int) env('PLATFORM_LIMIT_CARD', 30),
        'public' => (int) env('PLATFORM_LIMIT_PUBLIC', 60),
        'registration' => (int) env('PLATFORM_LIMIT_REGISTRATION', 10),
    ],
    // Cor de destaque padrão do portal público quando a organização não escolheu uma (#rrggbb).
    'default_color' => env('PORTAL_DEFAULT_COLOR', '#059669'),

    // Limite de pixels de uma imagem enviada (largura x altura); acima disso o envio é recusado.
    'images' => [
        'max_pixels' => max(1_000_000, (int) env('IMAGE_MAX_PIXELS', 40_000_000)),
    ],

    // Respostas com IA na central de ajuda (ADR 0006): perguntas por pessoa por dia e por organização por mês.
    'help_ai' => [
        'user_daily' => (int) env('HELP_AI_USER_DAILY', 10),
        'monthly' => (int) env('HELP_AI_MONTHLY', 300),
    ],

    // Dias que o pedido de aviso (push) de um jogo encerrado é guardado antes de `push:prune` apagá-lo (mínimo 1).
    'push_retention_days' => max(1, (int) env('PUSH_RETENTION_DAYS', 1)),
    'signed_url_minutes' => [
        'documents' => (int) env('SIGNED_URL_DOCUMENTS_MINUTES', 10),
        'photos' => (int) env('SIGNED_URL_PHOTOS_MINUTES', 30),
    ],

    /*
    | Landing page do produto (docs/14). Ela sempre existe em `/produto`. Com `root` ligado, a RAIZ dos hosts da landing
    | (`hosts`, separados por vírgula; sem valor usa o domínio-base e o www dele) também mostra a landing em vez do portal da
    | organização padrão. `contact_email` recebe o aviso dos contatos enviados pelo formulário (vazio = só guarda).
    */
    'landing' => [
        'root' => (bool) env('PLATFORM_LANDING_ROOT', false),
        'hosts' => env('PLATFORM_LANDING_HOSTS'),
        // Medição anônima de acessos (só com consentimento no navegador). Desligada = sem aviso e sem coleta.
        'analytics' => (bool) env('PLATFORM_LANDING_ANALYTICS', true),
    ],
    'contact_email' => env('PLATFORM_CONTACT_EMAIL'),

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
        'notify' => (bool) env('STATUS_NOTIFY', true),
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
