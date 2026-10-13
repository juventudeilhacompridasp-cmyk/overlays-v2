<?php

namespace App\Modules\Tenancy\Enums;

/**
 * Papel do usuário em uma organização. A matriz papel → permissões fica aqui (um lugar só).
 *
 * - Administrador: tudo, inclusive usuários.
 * - Organizador: competições, cadastros e operação de jogos.
 * - Operador (mesário): só opera os jogos (súmula, cronômetro...); o resto ele apenas consulta.
 * - Consulta: apenas visualiza o painel.
 * - Gestor de equipe: NÃO usa o painel do organizador; tem o próprio painel (`/gestor`), restrito às equipes a que está vinculado
 *   (`team_managers`). Não tem nenhuma `Ability`: nada do painel se abre por engano (ADR 0003).
 */
enum Role: string
{
    case Admin = 'admin';
    case Organizer = 'organizer';
    case Operator = 'operator';
    case Viewer = 'viewer';
    case TeamManager = 'manager';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Organizer => 'Organizador',
            self::Operator => 'Operador de jogo',
            self::Viewer => 'Consulta',
            self::TeamManager => 'Gestor de equipe',
        };
    }

    /** @return list<Ability> */
    public function abilities(): array
    {
        return match ($this) {
            self::Admin => Ability::cases(),
            self::Organizer => [Ability::ManageCompetitions, Ability::ManageRegistry, Ability::OperateGames],
            self::Operator => [Ability::OperateGames],
            self::Viewer, self::TeamManager => [],
        };
    }

    /**
     * Permissões finas equivalentes às abilities do papel (base dos perfis de sistema, ADR 0005).
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return array_map(Permission::forAbility(...), $this->abilities());
    }

    /** Papéis da equipe interna da organização (usam `/painel`); o gestor de equipe tem painel próprio. */
    public function usesPanel(): bool
    {
        return $this !== self::TeamManager;
    }

    public function can(Ability $ability): bool
    {
        return in_array($ability, $this->abilities(), true);
    }
}
