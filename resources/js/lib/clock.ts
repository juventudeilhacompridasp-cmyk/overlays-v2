import { useEffect, useState } from 'react';

export interface GameClock {
    state: 'not_started' | 'running' | 'stopped';
    period: number | null;
    base_seconds: number;
    running_since_ms: number | null;
}

/** Segundos decorridos no relógio do jogo. `skewMs` = relógio do cliente menos o do servidor. */
export function elapsedSeconds(clock: GameClock, nowMs: number, skewMs: number): number {
    if (clock.state !== 'running' || clock.running_since_ms === null) {
        return clock.base_seconds;
    }

    return clock.base_seconds + Math.max(0, Math.floor((nowMs - skewMs - clock.running_since_ms) / 1000));
}

export function formatClock(seconds: number): string {
    const minutes = Math.floor(seconds / 60);
    const rest = seconds % 60;

    return `${String(minutes).padStart(2, '0')}:${String(rest).padStart(2, '0')}`;
}

export const periodLabel = (period: number | null): string => {
    if (period === null) return '';
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
