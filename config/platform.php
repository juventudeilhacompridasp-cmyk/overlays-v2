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

];
