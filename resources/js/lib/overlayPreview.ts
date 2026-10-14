export type OverlayPreviewSlug = 'placar' | 'eventos' | 'escalacao' | 'tabela';

export interface OverlayPreviewLayers {
    lineup: boolean;
    standings: boolean;
    scoreboard?: boolean;
    lower_third?: boolean;
}

export interface OverlayPreviewStatus {
    label: string;
    tone: 'live' | 'automatic' | 'hidden' | 'waiting';
    description: string;
}

export function overlayPreviewStatus(slug: string, hasGame: boolean, layers: OverlayPreviewLayers): OverlayPreviewStatus {
    if (!hasGame) {
        return {
            label: 'Sem jogo',
            tone: 'waiting',
            description: 'Escolha o jogo no ar para alimentar esta tela.',
        };
    }

    if (slug === 'escalacao' && !layers.lineup) {
        return {
            label: 'Oculta',
            tone: 'hidden',
            description: 'A prévia está disponível; ligue a camada para exibi-la no OBS.',
        };
    }

    if (slug === 'tabela' && !layers.standings) {
        return {
            label: 'Oculta',
            tone: 'hidden',
            description: 'A prévia está disponível; ligue a camada para exibi-la no OBS.',
        };
    }

    if ((slug === 'placar' && layers.scoreboard === false) || (slug === 'eventos' && layers.lower_third === false)) {
        return {
            label: 'Oculta',
            tone: 'hidden',
            description: 'Foi ocultada pelo operador; ligue de novo para voltar ao ar no OBS.',
        };
    }

    if (slug === 'eventos') {
        return {
            label: 'Automática',
            tone: 'automatic',
            description: 'A faixa aparece somente quando um lance é lançado na súmula.',
        };
    }

    return {
        label: 'No ar',
        tone: 'live',
        description: 'Esta tela acompanha o estado atual do canal em tempo real.',
    };
}
