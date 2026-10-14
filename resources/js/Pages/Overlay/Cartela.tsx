import { Realtime } from '@/lib/echo';
import { OverlayState, useOverlay } from '@/lib/overlay';
import { overlayStyle } from '@/lib/overlayVisual';
import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { countdownLabel } from './Pregame';

/** Cartela de transmissão em tela cheia ("Começamos em breve", "Intervalo", "Voltamos já"), com contagem e patrocinadores. */
export default function Cartela({ token, state: initial, realtime }: { token: string; state: OverlayState; realtime: Realtime }) {
    const { state, skew } = useOverlay(token, initial, realtime);
    const appearance = state.appearance;
    const { title, subtitle, until_ms: until } = state.cartela;
    const visible = state.screens.cartela && title !== '';
    const [now, setNow] = useState(() => Date.now() - skew);

    useEffect(() => {
        const timer = window.setInterval(() => setNow(Date.now() - skew), 1000);

        return () => window.clearInterval(timer);
    }, [skew]);

    const remaining = until !== null ? Math.round((until - now) / 1000) : null;
    const logos = state.sponsors.items.slice(0, 6);

    return (
        <div className="fixed inset-0 flex flex-col items-center justify-center font-sans transition-opacity duration-700" style={{ ...overlayStyle(appearance), color: appearance.text_color, opacity: visible ? 1 : 0, background: visible ? `radial-gradient(circle at 30% 20%, ${appearance.accent_color}40, transparent 55%), linear-gradient(160deg, ${appearance.background_color}, #000000)` : 'transparent', textShadow: '0 2px 14px rgba(0,0,0,.6)' }} data-testid="overlay-cartela" data-visible={visible}>
            <Head title="Cartela" />
            {visible && (
                <div style={{ transform: `scale(${appearance.scale})` }} className="flex flex-col items-center text-center">
                    <div className="mb-6 h-2 w-40 rounded" style={{ backgroundColor: appearance.accent_color }} />
                    <h1 className="text-8xl font-black uppercase tracking-wide">{title}</h1>
                    {subtitle && <p className="mt-4 text-4xl font-semibold text-white/80">{subtitle}</p>}
                    {remaining !== null && (
                        <div className="mt-10 text-9xl font-black tabular-nums" style={{ color: appearance.accent_color }} data-testid="cartela-countdown">{countdownLabel(remaining)}</div>
                    )}
                    {logos.length > 0 && (
                        <div className="mt-16 flex items-center gap-8 rounded-2xl bg-white/95 px-10 py-4 shadow-xl">
                            {logos.map((sponsor) => <img key={sponsor.logo_url} src={sponsor.logo_url} alt={sponsor.name} className="max-h-16 max-w-[10rem] object-contain" />)}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
