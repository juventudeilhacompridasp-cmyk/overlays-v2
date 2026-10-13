<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pedidos de aviso push de jogos encerrados não ficam guardados.
Schedule::command('push:prune')->daily();

// Lembretes de escalação aos gestores de equipe (cada aviso sai uma única vez por jogo e gestor).
Schedule::command('managers:remind-lineups')->everyTenMinutes()->withoutOverlapping();
