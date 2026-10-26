/**
 * Controle remoto da transmissão (celular e tablet; código puro, testado em Node). A tela só desenha: escolha do canal,
 * limites do contador de acréscimos e o aviso "sem permissão" ficam aqui.
 */

/** Canal a mostrar: o último escolhido neste aparelho, se ainda existir; senão o primeiro. */
export function pickChannel<T extends { id: string }>(channels: T[], storedId: string | null): T | undefined {
    return channels.find((channel) => channel.id === storedId) ?? channels[0];
}

/** Acréscimos em minutos: nunca abaixo de 0 nem acima de 15 (o servidor também limita). */
export function nextStoppage(current: number, delta: number): number {
    return Math.max(0, Math.min(15, (Number.isFinite(current) ? current : 0) + delta));
}

/** Texto do estado de cada botão grande (a cor sozinha não diz o estado). */
export function tileHint(on: boolean, needsGame: boolean, hasGame: boolean): string {
    if (needsGame && !hasGame) return 'Escolha o jogo';

    return on ? 'NO AR' : 'Oculto';
}

/** Primeira mensagem de erro de uma resposta do servidor (erros por campo ou aviso geral), ou null. */
export function firstError(errors: Record<string, string> | undefined): string | null {
    const first = errors ? Object.values(errors)[0] : undefined;

    return typeof first === 'string' && first !== '' ? first : null;
}
