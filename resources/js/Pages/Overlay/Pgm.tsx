import { Realtime } from '@/lib/echo';
import { OverlayProvider, OverlayState } from '@/lib/overlay';
import { Head } from '@inertiajs/react';
import Bracket from './Bracket';
import Cartela from './Cartela';
import Compare from './Compare';
import Lineup from './Lineup';
import LowerThird from './LowerThird';
import Pregame from './Pregame';
import Scoreboard from './Scoreboard';
import Shootout from './Shootout';
import Social from './Social';
import SponsorBar from './SponsorBar';
import Sponsors from './Sponsors';
import Standings from './Standings';
import Stats from './Stats';
import Summary from './Summary';

/**
 * Monitor PGM do Modo operador: tudo o que o canal manda ao ar, empilhado numa única página com UMA conexão em tempo real.
 * Cada tela só aparece quando está ligada no canal, como no OBS. Não é uma fonte para o OBS (fica fora da lista de telas) e
 * não mostra campeonato nem overlays livres (cada um é uma fonte própria).
 */
export default function Pgm({ token, state, realtime }: { token: string; state: OverlayState; realtime: Realtime }) {
    const props = { token, state, realtime };

    return (
        <OverlayProvider token={token} initial={state} realtime={realtime}>
            <Head title="PGM" />
            <Sponsors {...props} />
            <Standings {...props} />
            <Lineup {...props} />
            <Pregame {...props} />
            <Compare {...props} />
            <Summary {...props} />
            <Bracket {...props} />
            <Stats {...props} />
            <Shootout {...props} />
            <Scoreboard {...props} />
            <Social {...props} />
            <SponsorBar {...props} />
            <Cartela {...props} />
            <LowerThird {...props} />
        </OverlayProvider>
    );
}
