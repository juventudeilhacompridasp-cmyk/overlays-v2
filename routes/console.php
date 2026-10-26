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
Schedule::command('documents:prune-access-log')->daily();
Schedule::command('activity:prune')->daily();
Schedule::command('documents:remind')->dailyAt('08:00')->withoutOverlapping();

// Perfis do Mercado sem revalidação há 60 dias saem da busca (o atleta pode publicar de novo).
Schedule::command('market:expire')->daily();

// Status page: sonda os componentes a cada minuto (o comando sai com erro se algum falhar, sem derrubar o agendador) e apaga o histórico antigo.
Schedule::command('status:probe')->everyMinute()->withoutOverlapping()->runInBackground();
Schedule::command('status:prune')->daily();

// Landing page: contadores da medição anônima com mais de 400 dias são apagados.
Schedule::command('marketing:prune-metrics')->daily();
