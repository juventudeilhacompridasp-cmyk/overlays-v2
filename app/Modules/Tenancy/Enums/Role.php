<?php

namespace App\Modules\Tenancy\Enums;

/**
 * Papel do usuário em uma organização. A matriz papel → permissões fica aqui (um lugar só).
 *
 * - Administrador: tudo, inclusive usuários.
 * - Organizador: competições, cadastros e operação de jogos.
 * - Operador (mesário): só opera os jogos (súmula, cronômetro...); o resto ele apenas consulta.
 * - Consulta: apenas visualiza o painel.
 */
enum Role: string
{
    case Admin = 'admin';
    case Organizer = 'organizer';
    case Operator = 'operator';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Organizer => 'Organizador',
            self::Operator => 'Operador de jogo',
            self::Viewer => 'Consulta',
        };
    }

    /** @return list<Ability> */
    public function abilities(): array
    {
        return match ($this) {
            self::Admin => Ability::cases(),
            self::Organizer => [Ability::ManageCompetitions, Ability::ManageRegistry, Ability::OperateGames],
            self::Operator => [Ability::OperateGames],
            self::Viewer => [],
        };
    }

    public function can(Ability $ability): bool
    {
        return in_array($ability, $this->abilities(), true);
    }
}
