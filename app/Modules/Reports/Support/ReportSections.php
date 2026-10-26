<?php

namespace App\Modules\Reports\Support;

use App\Modules\Reports\Enums\ReportKind;

/**
 * Catálogo das seções de cada relatório (ordem fixa = ordem no PDF e dos slides no overlay). A escolha da pessoa só
 * filtra: chave desconhecida é ignorada e lista vazia vale como "todas".
 */
final class ReportSections
{
    public const GAME = [
        'overview' => ['label' => 'Visão geral', 'icon' => 'chart'],
        'comparison' => ['label' => 'Comparativo das equipes', 'icon' => 'equal'],
        'periods' => ['label' => 'Placar por período', 'icon' => 'clock'],
        'timeline' => ['label' => 'Linha do tempo', 'icon' => 'list'],
        'lineups' => ['label' => 'Escalações', 'icon' => 'users'],
        'standings' => ['label' => 'Situação na tabela', 'icon' => 'trophy'],
        'form' => ['label' => 'Momento e confronto direto', 'icon' => 'bolt'],
    ];

    public const CHAMPIONSHIP = [
        'overview' => ['label' => 'Visão geral', 'icon' => 'chart'],
        'standings' => ['label' => 'Classificação', 'icon' => 'trophy'],
        'knockout' => ['label' => 'Mata-mata', 'icon' => 'flag'],
        'scorers' => ['label' => 'Artilharia', 'icon' => 'star'],
        'attack_defense' => ['label' => 'Ataque e defesa', 'icon' => 'shield'],
        'rounds' => ['label' => 'Placar por rodada', 'icon' => 'calendar'],
        'results' => ['label' => 'Destaques', 'icon' => 'bolt'],
        'form' => ['label' => 'Forma recente', 'icon' => 'target'],
        'discipline' => ['label' => 'Disciplina', 'icon' => 'card'],
    ];

    /** @return array<string, array{label: string, icon: string}> */
    public static function catalog(ReportKind $kind): array
    {
        return $kind === ReportKind::Game ? self::GAME : self::CHAMPIONSHIP;
    }

    /** @return list<string> as seções escolhidas, na ordem do catálogo */
    public static function pick(ReportKind $kind, mixed $sections): array
    {
        $catalog = array_keys(self::catalog($kind));
        $chosen = is_array($sections) ? array_map('strval', array_filter($sections, 'is_scalar')) : [];
        $picked = array_values(array_intersect($catalog, $chosen));

        return $picked === [] ? $catalog : $picked;
    }

    /** @return list<array{key: string, label: string}> para os formulários */
    public static function options(ReportKind $kind): array
    {
        return array_map(fn (string $key, array $item) => ['key' => $key, 'label' => $item['label']], array_keys(self::catalog($kind)), self::catalog($kind));
    }
}
