<?php

namespace App\Modules\Tenancy\Enums;

use LogicException;

/**
 * Catálogo de permissões finas (`modulo.acao`) que um perfil de acesso pode conter (ADR 0005).
 *
 * Nesta etapa cada permissão corresponde a uma `Ability` já aplicada nas rotas; as permissões novas
 * entram aqui junto com a rota/tela que as usa (nada é declarado sem ser aplicado).
 */
enum Permission: string
{
    case CompetitionsManage = 'competitions.manage';
    case RegistryManage = 'registry.manage';
    case GamesOperate = 'games.operate';
    case UsersManage = 'users.manage';

    public function label(): string
    {
        return $this->ability()->label();
    }

    /** Módulo (agrupa a matriz de perfis). */
    public function group(): string
    {
        return explode('.', $this->value)[0];
    }

    /** Ability legada equivalente (o `Gate` e o middleware `panel.can` continuam falando por ela). */
    public function ability(): Ability
    {
        return match ($this) {
            self::CompetitionsManage => Ability::ManageCompetitions,
            self::RegistryManage => Ability::ManageRegistry,
            self::GamesOperate => Ability::OperateGames,
            self::UsersManage => Ability::ManageUsers,
        };
    }

    public static function forAbility(Ability $ability): self
    {
        foreach (self::cases() as $permission) {
            if ($permission->ability() === $ability) {
                return $permission;
            }
        }

        throw new LogicException("Ability sem permissão: {$ability->value}");
    }
}
