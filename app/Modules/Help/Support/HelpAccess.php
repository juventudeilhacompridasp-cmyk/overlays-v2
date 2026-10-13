<?php

namespace App\Modules\Help\Support;

use App\Models\User;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Enums\Feature;
use App\Modules\Tenancy\Enums\Role;
use Illuminate\Support\Facades\Gate;

/**
 * Quem vê qual artigo: público (painel, gestor de equipe ou plataforma) × permissão exigida × recurso do pacote.
 * Não mostra ajuda de recurso que a organização não tem, nem de área que o perfil do usuário não acessa.
 */
final class HelpAccess
{
    /**
     * @param  list<HelpArticle>  $articles
     * @return list<HelpArticle>
     */
    public static function visible(array $articles, ?User $user, string $area): array
    {
        return array_values(array_filter($articles, fn (HelpArticle $article) => self::canSee($article, $user, $area)));
    }

    public static function canSee(HelpArticle $article, ?User $user, string $area): bool
    {
        if ($user === null) {
            return false;
        }

        $role = $user->role();
        $platform = $user->is_platform_admin === true;

        // A área escolhe o que cabe na tela; o usuário precisa ser mesmo daquele público.
        $allowed = match ($area) {
            'manager' => $article->audience === 'manager' && $role === Role::TeamManager,
            'athlete' => $article->audience === 'athlete' && $role === Role::Athlete,
            'platform' => $article->audience === 'platform' && $platform,
            default => ($article->audience === 'panel' && $role?->usesPanel() === true) || ($article->audience === 'platform' && $platform && $role?->usesPanel() === true),
        };

        if (! $allowed) {
            return false;
        }

        if ($article->ability !== null && ! Gate::forUser($user)->allows($article->ability)) {
            return false;
        }

        if ($article->feature !== null && $article->audience !== 'platform') {
            $feature = Feature::tryFrom($article->feature);

            return $feature !== null && CurrentOrganization::has($feature);
        }

        return true;
    }
}
