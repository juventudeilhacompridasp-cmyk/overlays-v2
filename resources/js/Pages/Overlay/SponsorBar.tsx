import { SponsorBarCard } from '@/Components/Overlay/SponsorBarCard';
import { Realtime } from '@/lib/echo';
import { OverlayState, useOverlay } from '@/lib/overlay';
import { overlayStyle } from '@/lib/overlayVisual';
import { barItems } from '@/lib/sponsorLook';
import { Head } from '@inertiajs/react';

/** Barra de patrocinadores 1500 x 200: um banner por vez (todos em loop), na posição e moldura escolhidas, só no ar quando a tela está ligada. */
export default function SponsorBar({ token, state: initial, realtime }: { token: string; state: OverlayState; realtime: Realtime }) {
    const { state } = useOverlay(token, initial, realtime);
    const live = state.screens.sponsor_bar === true;
    const hasItems = barItems(state.sponsors.items).length > 0;
    const appearance = state.appearance;
    const x = state.sponsors.bar_x ?? 50;
    const y = state.sponsors.bar_y ?? 91;
    const scale = state.sponsors.bar_scale ?? 100;

    return (
        <div className="fixed inset-0 font-sans" style={{ ...overlayStyle(appearance), color: appearance.text_color }} data-testid="overlay-sponsor-bar" data-visible={live && hasItems}>
            <Head title="Barra de patrocinadores" />
            {live && hasItems && (
                <div style={{ position: 'absolute', left: `${x}%`, top: `${y}%`, transform: `translate(-50%, -50%) scale(${scale / 100})` }}>
                    <SponsorBarCard sponsors={state.sponsors} appearance={appearance} />
                </div>
            )}
        </div>
    );
}
