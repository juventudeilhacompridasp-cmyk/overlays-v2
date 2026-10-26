import { Realtime } from '@/lib/echo';
import { OverlayProvider, OverlayRuntimeContext, OverlayRuntimeValue, OverlayState } from '@/lib/overlay';
import { Head } from '@inertiajs/react';
import { ReactNode, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import Bracket from './Bracket';
import Cartela from './Cartela';
import Compare from './Compare';
import Lineup from './Lineup';
import LowerThird from './LowerThird';
import Pregame from './Pregame';
import Report from './Report';
import Scoreboard from './Scoreboard';
import Shootout from './Shootout';
import Social from './Social';
import SponsorBar from './SponsorBar';
import Sponsors from './Sponsors';
import Standings from './Standings';
import Stats from './Stats';
import Summary from './Summary';

const PREVIEW_POLL_MS = 2000;

/**
 * PVW: em vez do Reverb (que mandaria o estado do ar e apagaria a prévia), consulta `/overlay/{token}/estado?pvw=chave` —
 * o estado com aquela camada ou tela ligada só em memória no servidor (exige sessão do painel). O painel troca a chave por
 * `postMessage` ({ type: 'pvw', key }), na mesma origem, sem recarregar a página.
 */
function PreviewProvider({ token, initial, children }: { token: string; initial: OverlayState; children: ReactNode }) {
    const [state, setState] = useState(initial);
    const key = useRef('');
    const skew = useRef(Date.now() - initial.server_time_ms);

    const load = useCallback(async () => {
        if (document.hidden) return;

        try {
            const response = await fetch(route('overlay.state', { channel: token, pvw: key.current }), { headers: { Accept: 'application/json' }, cache: 'no-store' });

            if (!response.ok) return;

            const next: OverlayState = await response.json();
            skew.current = Date.now() - next.server_time_ms;
            setState(next);
        } catch {
            // sem rede: tenta de novo no próximo ciclo
        }
    }, [token]);

    useEffect(() => {
        document.documentElement.style.background = 'transparent';
        document.body.style.background = 'transparent';
        const timer = window.setInterval(load, PREVIEW_POLL_MS);
        const onMessage = (event: MessageEvent) => {
            if (event.origin !== window.location.origin || event.data?.type !== 'pvw') return;

            key.current = typeof event.data.key === 'string' ? event.data.key : '';
            load();
        };
        window.addEventListener('message', onMessage);

        return () => {
            window.clearInterval(timer);
            window.removeEventListener('message', onMessage);
        };
    }, [load]);

    const value = useMemo<OverlayRuntimeValue>(() => ({ state, banner: null, connection: 'connected', skew: skew.current }), [state]);

    return <OverlayRuntimeContext.Provider value={value}>{children}</OverlayRuntimeContext.Provider>;
}

/**
 * Monitor do Modo operador: todas as telas empilhadas com UMA fonte de estado. Sem `preview` é o PGM (o que o canal manda ao ar,
 * por Reverb); com `preview` é o PVW (o que ficaria no ar com a camada escolhida, por consulta). Não é fonte do OBS (fica fora
 * da lista de telas) e não mostra campeonato nem overlays livres (cada um é uma fonte própria).
 */
export default function Pgm({ token, state, realtime, preview = false }: { token: string; state: OverlayState; realtime: Realtime; preview?: boolean }) {
    const props = { token, state, realtime };
    const screens = (
        <>
            <Head title={preview ? 'PVW' : 'PGM'} />
            <Sponsors {...props} />
            <Standings {...props} />
            <Lineup {...props} />
            <Pregame {...props} />
            <Compare {...props} />
            <Summary {...props} />
            <Bracket {...props} />
            <Stats {...props} />
            <Shootout {...props} />
            <Report {...props} />
            <Scoreboard {...props} />
            <Social {...props} />
            <SponsorBar {...props} />
            <Cartela {...props} />
            <LowerThird {...props} />
        </>
    );

    return preview ? <PreviewProvider token={token} initial={state}>{screens}</PreviewProvider> : <OverlayProvider token={token} initial={state} realtime={realtime}>{screens}</OverlayProvider>;
}
