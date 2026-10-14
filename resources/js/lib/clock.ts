import { useEffect, useState } from 'react';

export interface GameClock {
    state: 'not_started' | 'running' | 'paused' | 'stopped';
    period: number | null;
    base_seconds: number;
    running_since_ms: number | null;
    /** Basquete: o relógio mostra o tempo restante do período e para/volta dentro dele. */
    direction?: 'down';
    pausable?: boolean;
    offset_seconds?: number;
    period_seconds?: number;
}

export type ShotClock = Pick<GameClock, 'state' | 'period' | 'base_seconds' | 'running_since_ms'>;

/** Segundos decorridos no relógio do jogo. `skewMs` = relógio do cliente menos o do servidor. */
export function elapsedSeconds(clock: GameClock, nowMs: number, skewMs: number): number {
    if (clock.state !== 'running' || clock.running_since_ms === null) {
        return clock.base_seconds;
    }

    return clock.base_seconds + Math.max(0, Math.floor((nowMs - skewMs - clock.running_since_ms) / 1000));
}

/** Depois deste período vem um intervalo (e não o fim do jogo ou de uma prorrogação)? */
export const isInterval = (period: number | null, sport?: string): boolean => (sport === 'basketball' ? period !== null && period < 4 : period === 1);

/** O que o relógio mostra: o tempo decorrido (futebol) ou o restante do período (basquete). */
export function shownSeconds(clock: GameClock, elapsed: number): number {
    if (clock.direction === 'down' && clock.period_seconds !== undefined) {
        return Math.max(0, clock.period_seconds - (elapsed - (clock.offset_seconds ?? 0)));
    }

    return elapsed;
}

export function formatClock(seconds: number): string {
    const minutes = Math.floor(seconds / 60);
    const rest = seconds % 60;

    return `${String(minutes).padStart(2, '0')}:${String(rest).padStart(2, '0')}`;
}

export const periodLabel = (period: number | null, sport?: string): string => {
    if (period === null) return '';
    if (sport === 'basketball') return period <= 4 ? `${period}º quarto` : `Prorrogação ${period - 4}`;
    if (period <= 2) return `${period}º tempo`;

    return `Prorrogação ${period - 2}`;
};

/** Cronômetro que atualiza a cada segundo só enquanto está correndo. */
export function useGameClock(clock: GameClock, skewMs: number): number {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        setNow(Date.now());

        if (clock.state !== 'running') {
            return;
        }

        const timer = window.setInterval(() => setNow(Date.now()), 1000);

        return () => window.clearInterval(timer);
    }, [clock.state, clock.running_since_ms, clock.base_seconds]);

    return elapsedSeconds(clock, now, skewMs);
}

/** Segundos restantes da posse; a animação é local e para em zero. */
export function shotClockSeconds(clock: ShotClock, nowMs: number, skewMs: number): number {
    if (clock.state !== 'running' || clock.running_since_ms === null) return clock.base_seconds;

    return Math.max(0, clock.base_seconds - Math.floor((nowMs - skewMs - clock.running_since_ms) / 1000));
}

export function useShotClock(clock: ShotClock, skewMs: number): number {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        setNow(Date.now());
        if (clock.state !== 'running' || clock.base_seconds <= 0) return;
        const timer = window.setInterval(() => {
            const current = Date.now();
            setNow(current);
            if (shotClockSeconds(clock, current, skewMs) === 0) window.clearInterval(timer);
        }, 250);
        return () => window.clearInterval(timer);
    }, [clock.state, clock.running_since_ms, clock.base_seconds, skewMs]);

    return shotClockSeconds(clock, now, skewMs);
}
