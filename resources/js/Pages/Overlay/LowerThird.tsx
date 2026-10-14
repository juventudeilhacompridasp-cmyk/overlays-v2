import LowerThirdCard, { WIDE_STYLES } from '@/Components/Overlay/LowerThirdCard';
import { Realtime } from '@/lib/echo';
import { inkOn, isFullscreenCard, lowerThirdFrames, lowerThirdFreePosition, lowerThirdTone, teamAccent } from '@/lib/lowerThird';
import { OverlayState, useOverlay } from '@/lib/overlay';
import { overlayStyle } from '@/lib/overlayVisual';
import { Head } from '@inertiajs/react';
import { CSSProperties, useEffect, useRef, useState } from 'react';

export default function LowerThird({ token, state, realtime }: { token: string; state: OverlayState; realtime: Realtime }) {
    const { banner, state: current } = useOverlay(token, state, realtime);
    // Mantém a última faixa na tela durante a animação de saída.
    const [last, setLast] = useState(banner);
    const [shown, setShown] = useState(false);
    const box = useRef<HTMLDivElement>(null);
    const settings = current.lower_third_settings;
    const appearance = current.appearance;
    const wide = WIDE_STYLES.includes(settings.style);
    const instant = appearance.motion === 'none';

    useEffect(() => {
        if (banner) {
            setLast(banner);
            const frame = requestAnimationFrame(() => setShown(true));

            return () => cancelAnimationFrame(frame);
        }

        setShown(false);
    }, [banner]);

    // Entrada e saída pela animação escolhida (a saída toca os mesmos quadros ao contrário).
    useEffect(() => {
        const element = box.current;
        if (!element || !last) return;

        element.animate?.(lowerThirdFrames(settings.animation, settings.style === 'flash'), {
            duration: instant ? 1 : appearance.motion === 'subtle' ? 350 : 650,
            easing: settings.animation === 'bounce' && shown ? 'ease-out' : 'cubic-bezier(.16,1,.3,1)',
            fill: 'both',
            direction: shown ? 'normal' : 'reverse',
        });
    }, [shown, last?.id, settings.animation, settings.style]); // eslint-disable-line react-hooks/exhaustive-deps

    // Comando de ocultar os lances: nada fica na tela, nem a faixa que estava saindo.
    if (!current.layers.lower_third) {
        return <Head title="Lances" />;
    }

    const free = lowerThirdFreePosition(settings);
    const fullscreen = last !== null && last !== undefined && isFullscreenCard(settings, last.kind);
    const zoom = settings.text_scale === 100 ? undefined : settings.text_scale / 100;
    const align = settings.position === 'center' ? 'flex justify-center' : settings.position === 'right' ? 'flex justify-end' : '';
    const placement = fullscreen ? 'inset-0 grid place-items-center' : free ? 'inset-0' : settings.style === 'flash' ? 'inset-x-0 bottom-0 pb-24' : `inset-x-0 bottom-0 p-12 ${align}`;
    const origin = settings.style === 'flash' ? 'bottom center' : settings.position === 'center' ? 'bottom center' : settings.position === 'right' ? 'bottom right' : 'bottom left';
    const anchor: CSSProperties = free && !fullscreen ? { position: 'absolute', left: free.left, top: free.top } : {};

    return (
        <div className={`fixed font-sans ${placement}`} style={{ ...overlayStyle(appearance), color: appearance.text_color, textShadow: '0 1px 2px rgba(0,0,0,.55)' }}>
            <Head title="Lances" />
            {last && (
                <div style={{ ...anchor, transform: `scale(${appearance.scale})`, transformOrigin: fullscreen ? 'center' : free ? 'top left' : origin }}>
                    <div ref={box} data-testid="overlay-lowerthird" data-visible={shown} data-style={settings.style} data-animation={settings.animation} data-fullscreen={fullscreen} className={wide && !fullscreen && !free ? 'w-full' : 'inline-block'} style={zoom ? { zoom } : undefined}>
                        {fullscreen ? <FullscreenCard banner={last} /> : <LowerThirdCard style={settings.style} banner={last} appearance={appearance} />}
                    </div>
                </div>
            )}
        </div>
    );
}

/** Cartão em tela cheia: a cor do cartão, o jogador e a equipe, bem grandes no centro. */
function FullscreenCard({ banner }: { banner: NonNullable<ReturnType<typeof useOverlay>['banner']> }) {
    const tone = lowerThirdTone(banner.kind);
    const ink = inkOn(tone.accent);
    const name = banner.subtitle ?? banner.title;

    return (
        <div className="flex w-[34rem] flex-col items-center gap-3 rounded-3xl px-10 py-12 text-center shadow-2xl" style={{ backgroundColor: tone.accent, color: ink, textShadow: 'none' }} data-testid="overlay-fullscreen-card">
            <div className="text-7xl">{tone.icon}</div>
            <div className="text-3xl font-black uppercase tracking-[0.12em]">{banner.title}</div>
            <div className="text-5xl font-black leading-tight">{name}</div>
            {banner.minute !== null && <div className="text-2xl font-semibold opacity-80">{banner.minute}'</div>}
            {banner.team && <div className="mt-2 rounded-full px-5 py-1 text-xl font-bold uppercase" style={{ backgroundColor: teamAccent(banner), color: inkOn(teamAccent(banner)) }}>{banner.team}</div>}
        </div>
    );
}
