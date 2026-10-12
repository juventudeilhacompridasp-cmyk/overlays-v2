import { usePage } from '@inertiajs/react';

export type Ability = 'manage-competitions' | 'manage-registry' | 'operate-games' | 'manage-users';

/** Só para mostrar/esconder botões: quem decide de verdade é o servidor (middleware `panel.can`). */
export function useCan(ability: Ability): boolean {
    return Boolean(usePage().props.auth.can[ability]);
}
