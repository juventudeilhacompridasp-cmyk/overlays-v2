import { formatClock, isInterval, periodLabel } from '@/lib/clock';
import type { DataContext } from '@/lib/customBuilder';
import type { OverlayState } from '@/lib/overlay';

/** Dados ao vivo que os textos e imagens do overlay livre usam, a partir do estado do canal e do cronômetro já calculado. */
export function buildContext(state: OverlayState, seconds: number, now: Date, sponsorIndex = 0): DataContext {
    const game = state.game;
    const items = state.sponsors.items;
    const sponsor = items.length > 0 ? items[sponsorIndex % items.length] : null;
    const side = (team: NonNullable<OverlayState['game']>['home'], score: number) => (team ? { name: team.name, short: team.short_name || team.name.slice(0, 3).toUpperCase(), score, logo: team.logo_url } : null);
    let clock = '';
    let period = '';

    if (game) {
        if (game.finished) clock = 'FIM';
        else if (game.clock.state === 'running' || game.clock.state === 'paused') clock = formatClock(seconds);
        else if (game.clock.state === 'stopped') clock = isInterval(game.clock.period, game.sport) ? 'INTERVALO' : 'FIM DO TEMPO';
        period = game.clock.period ? periodLabel(game.clock.period, game.sport) : '';
    }

    return {
        home: game ? side(game.home, game.home_score) : null,
        away: game ? side(game.away, game.away_score) : null,
        clock, period,
        competition: game?.competition ?? '', division: game?.division ?? '', stage: game?.stage ?? '', venue: game?.venue ?? '',
        sponsor: sponsor ? { name: sponsor.name, logo: sponsor.logo_url } : null,
        time: now.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }),
        table: state.custom_table ?? null,
    };
}
