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

];
