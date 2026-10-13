import { usePage } from '@inertiajs/react';

export type Ability = 'manage-competitions' | 'manage-registry' | 'operate-games' | 'manage-users' | 'manage-market';

/** Só para mostrar/esconder botões: quem decide de verdade é o servidor (middleware `panel.can`). */
export function useCan(ability: Ability): boolean {
    return Boolean(usePage().props.auth.can[ability]);
}

export type FeatureKey = 'online_registration' | 'athlete_cards' | 'reports' | 'overlays' | 'custom_domain' | 'white_label' | 'embeds' | 'push' | 'team_portal' | 'sheet_import' | 'market';

/** O pacote da organização inclui o recurso? Só para esconder menus/botões; o servidor barra de verdade. */
export function useFeature(feature: FeatureKey): boolean {
    return usePage().props.features.includes(feature);
}
