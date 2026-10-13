<?php

return [

    /*
    | Notificações push para quem acompanha jogos e equipes (Web Push / VAPID). Sem as chaves o recurso fica
    | desligado. Gere o par com `php artisan webpush:keys` e guarde a chave privada como segredo.
    */
    'public_key' => env('VAPID_PUBLIC_KEY'),
    'private_key' => env('VAPID_PRIVATE_KEY'),
    'subject' => env('VAPID_SUBJECT', 'mailto:contato@example.com'),

];
