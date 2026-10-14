import { Realtime } from '@/lib/echo';
import { OverlayState, useOverlay } from '@/lib/overlay';
import { overlayMotion, overlayOrigin, overlayPosition, overlayStyle } from '@/lib/overlayVisual';
import { Head } from '@inertiajs/react';
import QRCode from 'qrcode';
import { useEffect, useState } from 'react';

const ROTATE_MS = 6000;

/** Chamada para as redes sociais: @, site e QR code do link, rodando uma linha por vez. */
export default function Social({ token, state: initial, realtime }: { token: string; state: OverlayState; realtime: Realtime }) {
    const { state } = useOverlay(token, initial, realtime);
    const { items, url } = state.social;
    const appearance = state.appearance;
    const [index, setIndex] = useState(0);
    const [qr, setQr] = useState<string | null>(null);
    const visible = state.screens.social && (items.length > 0 || url !== null);

    useEffect(() => {
        if (items.length < 2) return;
        const timer = window.setInterval(() => setIndex((current) => (current + 1) % items.length), ROTATE_MS);

        return () => window.clearInterval(timer);
    }, [items.length]);

    useEffect(() => {
        let alive = true;
        if (!url) {
            setQr(null);

            return;
        }
        QRCode.toDataURL(url, { margin: 1, width: 240, errorCorrectionLevel: 'M' }).then((data) => alive && setQr(data)).catch(() => alive && setQr(null));

        return () => {
            alive = false;
        };
    }, [url]);

    const current = items.length > 0 ? items[index % items.length] : null;

    return (
        <div className={`fixed inset-0 flex p-10 font-sans transition-opacity duration-500 ${overlayPosition(appearance.position)}`} style={{ ...overlayStyle(appearance), color: appearance.text_color, opacity: visible ? 1 : 0, textShadow: '0 1px 4px rgba(0,0,0,.6)' }} data-testid="overlay-social" data-visible={visible}>
            <Head title="Redes sociais" />
            {visible && (
                <div style={{ transform: `scale(${appearance.scale})`, transformOrigin: overlayOrigin(appearance.position) }}>
                    <div className={overlayMotion(appearance.motion)}>
                        <div className="flex items-center gap-6 overflow-hidden rounded-2xl border border-white/15 py-3 pl-8 pr-3 shadow-2xl" style={{ background: `linear-gradient(135deg, ${appearance.background_color}f5, ${appearance.background_color}df)` }}>
                            {current && (
                                <div key={index} className={`min-w-[18rem] ${appearance.motion === 'none' ? '' : 'overlay-motion-subtle'}`}>
                                    {current.label && <div className="text-sm font-black uppercase tracking-[0.25em]" style={{ color: appearance.accent_color }}>{current.label}</div>}
                                    <div className="text-4xl font-black tracking-wide" data-testid="social-line">{current.value}</div>
                                </div>
                            )}
                            {qr && <img src={qr} alt="QR code" className="h-28 w-28 flex-none rounded-lg bg-white p-1" data-testid="social-qr" />}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
