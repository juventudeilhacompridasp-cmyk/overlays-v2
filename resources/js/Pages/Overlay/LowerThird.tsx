import LowerThirdCard, { WIDE_STYLES } from '@/Components/Overlay/LowerThirdCard';
import { Realtime } from '@/lib/echo';
import { lowerThirdFrames } from '@/lib/lowerThird';
import { OverlayState, useOverlay } from '@/lib/overlay';
import { overlayStyle } from '@/lib/overlayVisual';
import { Head } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

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

    const placement = settings.style === 'flash' ? 'inset-x-0 bottom-0 pb-24' : wide ? 'inset-x-0 bottom-0 p-12' : 'inset-x-0 bottom-0 p-12';

    return (
        <div className={`fixed font-sans ${placement}`} style={{ ...overlayStyle(appearance), color: appearance.text_color, textShadow: '0 1px 2px rgba(0,0,0,.55)' }}>
            <Head title="Lances" />
            {last && (
                <div style={{ transform: `scale(${appearance.scale})`, transformOrigin: settings.style === 'flash' ? 'bottom center' : 'bottom left' }}>
                    <div ref={box} data-testid="overlay-lowerthird" data-visible={shown} data-style={settings.style} data-animation={settings.animation} className={wide ? 'w-full' : 'inline-block'}>
                        <LowerThirdCard style={settings.style} banner={last} appearance={appearance} />
                    </div>
                </div>
            )}
        </div>
    );
}
