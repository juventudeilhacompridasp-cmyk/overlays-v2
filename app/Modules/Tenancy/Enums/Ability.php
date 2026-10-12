<?php

namespace App\Modules\Tenancy\Enums;

/** Permissões do painel. Ver (GET) exige apenas ser membro da organização; cada ability cobre uma área de escrita. */
enum Ability: string
{
    case ManageCompetitions = 'manage-competitions';
    case ManageRegistry = 'manage-registry';
    case OperateGames = 'operate-games';
    case ManageUsers = 'manage-users';

    public function label(): string
    {
        return match ($this) {
            self::ManageCompetitions => 'Competições (edições, divisões, fases, tabela, resultados)',
            self::ManageRegistry => 'Cadastros (equipes, atletas, comissão, estádios, elencos)',
            self::OperateGames => 'Operar jogos (escalação, súmula, cronômetro, pênaltis, encerrar)',
            self::ManageUsers => 'Usuários e papéis',
        };
    }
}
