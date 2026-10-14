import Frame, { EmbedOptions, muted } from '@/Components/Embed/Frame';
import { GameClock, formatClock, isInterval, periodLabel, shownSeconds, useGameClock } from '@/lib/clock';
import { ConnectionState, Realtime, subscribeToGame } from '@/lib/echo';
import { Head } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { Snapshot } from '@/Pages/Public/GameLive';

const POLL_MS = 8000;

export default function Game({ options, snapshot: initial, realtime, portal_url }: { options: EmbedOptions; snapshot: Snapshot; realtime: Realtime; portal_url: string }) {
    const [snapshot, setSnapshot] = useState(initial);
    const [connection, setConnection] = useState<ConnectionState>('connecting');
    const skew = useRef(0);

    useEffect(() => {
        skew.current = Date.now() - initial.server_time_ms;
    }, [initial.server_time_ms]);

    useEffect(() => {
        let cancel: (() => void) | undefined;
        let cancelled = false;

        subscribeToGame<Snapshot>(realtime, initial.id, setSnapshot, setConnection)
            .then((stop) => (cancelled ? stop() : (cancel = stop)))
            .catch(() => setConnection('disconnected'));

        return () => {
            cancelled = true;
            cancel?.();
        };
    }, [initial.id]);

    // Contingência: sem WebSocket, consulta o estado a cada poucos segundos (e para quando o jogo termina).
    useEffect(() => {
        if (connection === 'connected' || snapshot.finished) return;

        const timer = window.setInterval(async () => {
            try {
                const response = await fetch(route('public.games.state', initial.id), { headers: { Accept: 'application/json' } });
                if (response.ok) setSnapshot(await response.json());
            } catch {
                // sem rede: tenta de novo no próximo ciclo
            }
        }, POLL_MS);

        return () => window.clearInterval(timer);
    }, [connection, snapshot.finished, initial.id]);

    const seconds = shownSeconds(snapshot.clock as GameClock, useGameClock(snapshot.clock, skew.current));
    const home = snapshot.home?.name ?? 'A definir';
    const away = snapshot.away?.name ?? 'A definir';

    let status: string;
    if (snapshot.finished) status = 'Encerrado';
    else if (snapshot.sets) status = snapshot.sets.length > 0 && snapshot.status !== 'scheduled' ? `Set ${snapshot.sets.length}` : snapshot.status === 'scheduled' ? 'Em breve' : snapshot.status_label;
    else if (snapshot.clock.state === 'not_started') status = snapshot.status === 'scheduled' ? 'Em breve' : snapshot.status_label;
    else if (snapshot.clock.state === 'stopped') status = isInterval(snapshot.clock.period, snapshot.sport) ? 'Intervalo' : `Fim do ${periodLabel(snapshot.clock.period, snapshot.sport)}`;
    else status = `${formatClock(seconds)} · ${periodLabel(snapshot.clock.period, snapshot.sport)}`;

    const live = !snapshot.finished && snapshot.status === 'in_progress';
    const currentSet = snapshot.sets && snapshot.sets.length > 0 ? snapshot.sets[snapshot.sets.length - 1] : null;

    return (
        <Frame options={options} footer={{ label: 'Ver o jogo', href: portal_url }}>
            <Head title={`${home} x ${away}`} />
            <div className="flex items-center justify-between gap-2 px-3 py-3">
                <div className="flex min-w-0 flex-1 items-center justify-end gap-2 text-right font-semibold">
                    <span className="truncate">{home}</span>
                    {snapshot.home?.logo_url && <img src={snapshot.home.logo_url} alt="" className="h-8 w-8 object-contain" />}
                </div>
                <div className="rounded px-3 py-1 text-center text-2xl font-extrabold tabular-nums text-white" style={{ backgroundColor: 'var(--accent)' }} data-testid="embed-score">
                    {snapshot.home_score} x {snapshot.away_score}
                </div>
                <div className="flex min-w-0 flex-1 items-center gap-2 font-semibold">
                    {snapshot.away?.logo_url && <img src={snapshot.away.logo_url} alt="" className="h-8 w-8 object-contain" />}
                    <span className="truncate">{away}</span>
                </div>
            </div>
            <div className={`pb-2 text-center text-xs ${muted(options)}`} data-testid="embed-status">
                {live && <span className="mr-1 inline-block h-2 w-2 rounded-full bg-red-500" aria-hidden="true" />}
                {status}
                {currentSet && !snapshot.finished && ` · ${currentSet.home}-${currentSet.away}`}
                {snapshot.fouls && ` · faltas ${snapshot.fouls.home}-${snapshot.fouls.away}`}
                {snapshot.timeouts && ` · tempos ${snapshot.timeouts.home}-${snapshot.timeouts.away}`}
            </div>
        </Frame>
    );
}
