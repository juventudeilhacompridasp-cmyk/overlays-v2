/**
 * Modo operador da Transmissão (código puro, testado em Node): quais controles existem, atalhos de teclado e contadores.
 * A tela só desenha; as regras ficam aqui para serem testadas.
 */
export type OperatorLayerKey = 'scoreboard' | 'lower_third' | 'lineup' | 'standings' | 'sponsors';

/** Camadas grandes do "No ar", na ordem dos atalhos 1 a 5. `needsGame` = só liga com um jogo no ar. */
export const OPERATOR_LAYERS: { key: OperatorLayerKey; label: string; hotkey: string; needsGame: boolean }[] = [
    { key: 'scoreboard', label: 'Placar', hotkey: '1', needsGame: false },
    { key: 'lower_third', label: 'Lances', hotkey: '2', needsGame: false },
    { key: 'lineup', label: 'Escalação', hotkey: '3', needsGame: true },
    { key: 'standings', label: 'Tabela', hotkey: '4', needsGame: true },
    { key: 'sponsors', label: 'Patrocinadores', hotkey: '5', needsGame: false },
];

/** Telas extras (um clique liga e desliga), com nome curto para caber lado a lado. */
export const OPERATOR_SCREENS: { key: string; label: string; needsGame: boolean }[] = [
    { key: 'pregame', label: 'Pré-jogo', needsGame: true },
    { key: 'compare', label: 'Comparativo', needsGame: true },
    { key: 'summary', label: 'Resumo', needsGame: true },
    { key: 'bracket', label: 'Chaveamento', needsGame: true },
    { key: 'stats', label: 'Estatísticas', needsGame: true },
    { key: 'shootout', label: 'Pênaltis', needsGame: true },
    { key: 'cartela', label: 'Cartela', needsGame: false },
    { key: 'social', label: 'Redes sociais', needsGame: false },
    { key: 'sponsor_bar', label: 'Barra 1500x200', needsGame: false },
];

/** Nomes curtos das telas de campeonato (cabem sem reticências nos botões do modo operador). */
export const OPERATOR_CHAMPIONSHIP_LABELS: Record<string, string> = {
    standings: 'Tabela', fixtures: 'Próximos', results: 'Resultados', scorers: 'Artilharia', discipline: 'Cartões', matchcard: 'Destaque', ticker: 'Faixa', champion: 'Campeão',
};

/** Avisos de um clique no OBS (mesmos do estúdio de lances). */
export const OPERATOR_NOTICES: { title: string; subtitle?: string }[] = [
    { title: 'VAR', subtitle: 'Lance em análise' },
    { title: 'PÊNALTI', subtitle: 'Penalidade máxima' },
    { title: 'CARTÃO EM ANÁLISE' },
    { title: 'JOGO PARALISADO', subtitle: 'Aguarde' },
    { title: 'INTERVALO' },
    { title: 'FIM DE JOGO' },
];

/** Cartelas prontas: texto, subtítulo e minutos da contagem (0 = sem contagem). */
export const OPERATOR_CARTELAS: { label: string; title: string; subtitle: string; minutes: number }[] = [
    { label: 'Começamos em breve', title: 'Começamos em breve', subtitle: 'Fique ligado na transmissão', minutes: 10 },
    { label: 'Intervalo', title: 'Intervalo', subtitle: 'Voltamos já', minutes: 15 },
    { label: 'Voltamos já', title: 'Voltamos já', subtitle: '', minutes: 0 },
    { label: 'Fim', title: 'Obrigado por assistir', subtitle: 'Até o próximo jogo', minutes: 0 },
];

export type HotkeyAction = { type: 'layer'; key: OperatorLayerKey } | { type: 'lineup'; command: 'next' | 'previous' | 'reset' } | { type: 'exit' };

/** Atalhos: 1 a 5 ligam/desligam as camadas, setas passam a escalação, R reinicia e Esc sai. Nunca disparam enquanto se digita. */
export function hotkeyAction(key: string, target: { tagName?: string; isContentEditable?: boolean } | null, modifiers: { ctrl?: boolean; alt?: boolean; meta?: boolean } = {}): HotkeyAction | null {
    const tag = target?.tagName?.toLowerCase();

    if (modifiers.ctrl || modifiers.alt || modifiers.meta) return null;
    if (tag === 'input' || tag === 'textarea' || tag === 'select' || target?.isContentEditable) return key === 'Escape' ? { type: 'exit' } : null;

    const layer = OPERATOR_LAYERS.find((item) => item.hotkey === key);

    if (layer) return { type: 'layer', key: layer.key };
    if (key === 'ArrowRight') return { type: 'lineup', command: 'next' };
    if (key === 'ArrowLeft') return { type: 'lineup', command: 'previous' };
    if (key === 'r' || key === 'R') return { type: 'lineup', command: 'reset' };
    if (key === 'Escape') return { type: 'exit' };

    return null;
}

/** Contador das estatísticas: nunca abaixo de 0 nem acima de 999. */
export function bump(value: number, delta: number): number {
    return Math.max(0, Math.min(999, (Number.isFinite(value) ? value : 0) + delta));
}

/** Texto da etapa da escalação: manual mostra "etapa x de y"; automático diz que passa sozinho. */
export function stepLabel(mode: 'auto' | 'manual', step: number, total: number): string {
    return mode === 'manual' ? `Etapa ${Math.min(Math.max(0, step), Math.max(1, total) - 1) + 1} de ${Math.max(1, total)}` : 'Passando sozinho';
}

/** Quantas camadas/telas estão no ar (para o resumo da barra superior). */
export function liveCount(...maps: Record<string, boolean>[]): number {
    return maps.reduce((sum, map) => sum + Object.values(map).filter(Boolean).length, 0);
}
