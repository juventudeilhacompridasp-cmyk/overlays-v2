import CustomCanvas from '@/Components/Overlay/CustomCanvas';
import { buildContext } from '@/lib/customContext';
import { BuilderItem } from '@/lib/customBuilder';
import { shownSeconds, useGameClock } from '@/lib/clock';
import { Realtime } from '@/lib/echo';
import { OverlayState, useOverlay } from '@/lib/overlay';
import { nextSponsorIndex, overlayFontFamily } from '@/lib/overlayVisual';
import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/** Overlay livre (Builder) no OBS: o desenho da organização, ligado e desligado pelo canal, com dados ao vivo do jogo no ar. */
export default function Custom({ token, state: initial, item, realtime }: { token: string; state: OverlayState; item: BuilderItem; realtime: Realtime }) {
    const { state, skew } = useOverlay(token, initial, realtime);
    const game = state.game;
    const elapsed = useGameClock(game?.clock ?? { state: 'not_started', period: null, base_seconds: 0, running_since_ms: null }, skew);
    const seconds = game ? shownSeconds(game.clock, elapsed) : elapsed;
    const [now, setNow] = useState(() => new Date());
    const [sponsorIndex, setSponsorIndex] = useState(0);
    const count = state.sponsors.items.length;
    const live = state.custom_live?.[item.id] === true;

    // Hora (minuto a minuto) e rotação do patrocinador ativo, só quando algum texto/imagem usa esses dados.
    useEffect(() => {
        const timer = window.setInterval(() => setNow(new Date()), 15_000);

        return () => window.clearInterval(timer);
    }, []);
    useEffect(() => {
        if (count < 2) return;
        const timer = window.setInterval(() => setSponsorIndex((current) => nextSponsorIndex(current, count)), Math.max(3, state.sponsors.seconds) * 1000);

        return () => window.clearInterval(timer);
    }, [count, state.sponsors.seconds]);

    return (
        <div className="fixed left-0 top-0 font-sans" style={{ color: state.appearance.text_color }} data-testid="overlay-custom" data-live={live}>
            <Head title={item.name} />
            <CustomCanvas item={item} ctx={buildContext(state, seconds, now, sponsorIndex)} live={live} instant={state.appearance.motion === 'none'} fontFamily={overlayFontFamily(state.appearance.font)} />
        </div>
    );
}
