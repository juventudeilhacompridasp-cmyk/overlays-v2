<?php

namespace App\Modules\Broadcast\Actions;

use App\Modules\Broadcast\Models\OverlayStyleModel;
use App\Modules\Broadcast\Support\ChampionshipStyle;
use InvalidArgumentException;

/** Guarda um estilo dos overlays de campeonato ("Meus modelos"), sempre pelo saneamento do contrato. */
final class SaveOverlayStyleModel
{
    /** @param  array<mixed>  $options */
    public function handle(string $name, array $options): OverlayStyleModel
    {
        $name = trim($name);
        $clean = ChampionshipStyle::sanitize($options);

        if ($name === '' || mb_strlen($name) > 40) {
            throw new InvalidArgumentException('Informe um nome de até 40 caracteres.');
        }

        if ($clean === []) {
            throw new InvalidArgumentException('O modelo não tem nenhuma opção de estilo válida.');
        }

        $existing = OverlayStyleModel::query()->where('name', $name)->first();

        if ($existing === null && OverlayStyleModel::query()->count() >= OverlayStyleModel::LIMIT) {
            throw new InvalidArgumentException('A organização já tem '.OverlayStyleModel::LIMIT.' modelos. Exclua algum para salvar outro.');
        }

        if ($existing !== null) {
            $existing->update(['options' => $clean]);

            return $existing;
        }

        return OverlayStyleModel::query()->create(['name' => $name, 'options' => $clean]);
    }
}
