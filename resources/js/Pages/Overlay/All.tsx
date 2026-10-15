import { Realtime } from '@/lib/echo';
import { OverlayProvider, OverlayState } from '@/lib/overlay';
import { Head } from '@inertiajs/react';
import Lineup from './Lineup';
import LowerThird from './LowerThird';
import Scoreboard from './Scoreboard';
import SponsorBar from './SponsorBar';
import Sponsors from './Sponsors';
import Standings from './Standings';
import Stats from './Stats';

/**
 * Uma única fonte do OBS com todas as camadas empilhadas (cada uma só aparece quando está ligada
 * no canal). As camadas compartilham uma assinatura em tempo real e um polling de contingência.
 */
export default function All({ token, state, realtime }: { token: string; state: OverlayState; realtime: Realtime }) {
    return (
        <OverlayProvider token={token} initial={state} realtime={realtime}>
            <>
            <Head title="Todas as camadas" />
            <Sponsors token={token} state={state} realtime={realtime} />
            <Standings token={token} state={state} realtime={realtime} />
            <Lineup token={token} state={state} realtime={realtime} />
            <Stats token={token} state={state} realtime={realtime} />
            <Scoreboard token={token} state={state} realtime={realtime} />
            <SponsorBar token={token} state={state} realtime={realtime} />
            <LowerThird token={token} state={state} realtime={realtime} />
            </>
        </OverlayProvider>
    );
}
