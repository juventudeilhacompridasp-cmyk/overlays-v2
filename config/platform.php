<?php

return [

    /*
    | Organização usada enquanto a multi-tenancy completa (subdomínios, papéis) não existe.
    | Toda tabela de domínio já carrega organization_id para permitir a evolução sem retrabalho.
    */
    'default_organization_slug' => env('DEFAULT_ORGANIZATION_SLUG', 'default'),

];
