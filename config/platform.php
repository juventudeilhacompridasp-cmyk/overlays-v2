<?php

return [

    /*
    | Organização usada enquanto a multi-tenancy completa (subdomínios, papéis) não existe.
    | Toda tabela de domínio já carrega organization_id para permitir a evolução sem retrabalho.
    */
    'default_organization_slug' => env('DEFAULT_ORGANIZATION_SLUG', 'default'),

    /*
    | Chave do hash do CPF (LGPD: o CPF não é guardado em claro). Se vazia, usa a APP_KEY.
    | Mantenha estável: trocar a chave invalida a busca por CPF dos cadastros existentes.
    */
    'document_hash_key' => env('DOCUMENT_HASH_KEY'),

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
