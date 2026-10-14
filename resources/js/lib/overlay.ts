import { GameClock, ShotClock } from '@/lib/clock';
import { ConnectionState, Realtime, subscribeToChannel } from '@/lib/echo';
import './artFonts';
import { LowerThirdBannerData, LowerThirdSettings } from './lowerThird';
import { useEffect, useRef, useState } from 'react';
import type { BarLook, SponsorItem, SponsorLook } from './sponsorLook';
import type { StatsState } from './statsLook';

export interface OverlayTeam {
    name: string;
    short_name: string | null;
    logo_url: string | null;
    color: string | null;
}

export interface OverlayAppearance {
    preset: 'arena' | 'classic' | 'minimal' | 'glass' | 'contrast' | 'neon' | 'ribbon' | 'gradient';
    accent_color: string;
    background_color: string;
    text_color: string;
    position: 'top-left' | 'top-center' | 'top-right' | 'bottom-left' | 'bottom-center' | 'bottom-right';
    scale: number;
    motion: 'dynamic' | 'subtle' | 'none';
    layout: 'bar' | 'stacked' | 'compact' | 'wide';
    scorers: boolean;
    font: string;
    /** Posição livre em % da tela; nulo = vale `position`. */
    x?: number | null;
    y?: number | null;
    text_scale?: number;
    show_badge?: boolean;
    radius?: number | null;
    surface?: number;
    accent_size?: number;
    shadow?: 'none' | 'soft' | 'strong';
    entrance?: 'none' | 'fade' | 'slide' | 'zoom' | 'assemble';
    entrance_speed?: number;
    period_scale?: number;
    extra_time_scale?: number;
    goal_celebration?: boolean;
    goal_text?: string;
    goal_word_seconds?: number;
    goal_team_seconds?: number;
    goal_animation?: 'typewriter' | 'bounce' | 'sweep';
}

export interface OverlayFontFace {
    family: string;
    url: string;
    format: 'woff2' | 'woff' | 'ttf' | 'otf';
}

export interface OverlayGame {
    id: string;
    status: string;
    status_label: string;
    finished: boolean;
    home: OverlayTeam | null;
    away: OverlayTeam | null;
    home_score: number;
    away_score: number;
    home_penalties: number | null;
    away_penalties: number | null;
    scheduled_at: string | null;
    venue: string | null;
    competition: string;
    division: string;
    stage: string;
    clock: GameClock;
    shot_clock: ShotClock | null;
    sport: string;
    score_model: string;
    quarters: { period: number; home: number; away: number }[] | null;
    sets: { home: number; away: number; winner: 'home' | 'away' | null; finished: boolean }[] | null;
    fouls: { home: number; away: number; limit: number; period: number } | null;
    timeouts: { home: number; away: number; limit: number; period: number } | null;
    goals: { side: 'home' | 'away' | null; player: string | null; minute: number | null; own_goal: boolean }[];
}

export interface LineupSide {
    name: string;
    logo_url: string | null;
    color: string | null;
    formation: string | null;
    formation_ready: boolean;
    starters: { shirt: number | null; name: string; position: string | null; position_label: string | null; x: number | null; y: number | null; captain: boolean; photo_url: string | null; photo_large_url: string | null }[];
    bench: { shirt: number | null; name: string; captain: boolean; photo_url: string | null; photo_large_url: string | null }[];
    coach: string | null;
}

export interface LineupPublicSettings {
    presentation: 'sequence' | 'list' | 'formation' | 'cards' | 'individual' | 'show';
    list_seconds: number;
    team: 'home' | 'away';
    individual_mode: 'auto' | 'manual';
    individual_seconds: number;
    individual_scope: 'starters' | 'all';
    sponsor_banner: boolean;
    layout?: 'classic' | 'tunnel' | 'poster' | 'sidebar' | 'stadium' | 'matchday';
    style?: 'editorial' | 'cards' | 'glass' | 'premier' | 'champions' | 'diagonal';
    animation?: 'slide' | 'wipe' | 'rise' | 'zoom' | 'split' | 'cascade';
    formation_mark?: 'photo' | 'number';
    formation_photo_size?: number;
    sponsor_size?: number;
    x?: number | null;
    y?: number | null;
    surface?: number;
    radius?: number | null;
    text_scale?: number;
    step: number;
    anchor_ms: number;
}

export interface SponsorsState extends Partial<SponsorLook>, Partial<BarLook> {
    enabled: boolean;
    seconds: number;
    items: SponsorItem[];
}

export interface BracketSide {
    name: string;
    short: string | null;
    logo: string | null;
    score: number | null;
    winner: boolean;
}

export interface OverlayState {
    channel: string;
    appearance: OverlayAppearance;
    font_face: OverlayFontFace | null;
    lineup_settings: LineupPublicSettings;
    sponsors: SponsorsState;
    lower_third_settings: LowerThirdSettings;
    layers: { lineup: boolean; standings: boolean; scoreboard: boolean; lower_third: boolean };
    championship: Record<string, boolean>;
    stoppage: number;
    social: { items: { label: string; value: string }[]; url: string | null };
    screens: { pregame: boolean; compare: boolean; cartela: boolean; summary: boolean; bracket: boolean; social: boolean; sponsor_bar: boolean; stats: boolean };
    stats: StatsState | null;
    custom_live: Record<string, boolean>;
    cartela: { title: string; subtitle: string; until_ms: number | null };
    summary: {
        headline: string;
        cards: { side: 'home' | 'away'; kind: string; player: string | null; minute: number | null }[] | null;
        mvp: { name: string; shirt: number | null; team: string | null; side: 'home' | 'away' | null } | null;
    } | null;
    bracket: {
        title: string;
        rounds: { label: string; ties: { a: BracketSide; b: BracketSide; decided: boolean; live: boolean; penalties: { a: number; b: number } | null }[] }[];
        champion: string | null;
    } | null;
    compare: {
        standings: { title: string; home: { position: number; points: number; played: number; of: number } | null; away: { position: number; points: number; played: number; of: number } | null } | null;
        form: { home: string[]; away: string[] } | null;
        head_to_head: { home_wins: number; draws: number; away_wins: number } | null;
    } | null;
    game: OverlayGame | null;
    lineup: { home: LineupSide; away: LineupSide } | null;
    standings: {
        title: string;
        rows: { position: number; team: string; logo_url: string | null; points: number; played: number; goal_difference: number; won: number; drawn: number; lost: number; color: string | null; highlight: boolean }[];
    } | null;
    server_time_ms: number;
}

export type Banner = LowerThirdBannerData;

const POLL_MS = 3000;

/**
 * Estado de um canal de transmissão: recebe o estado completo pelo Reverb e, se o WebSocket não conectar,
 * consulta `/overlay/{token}/estado` a cada poucos segundos. Também guarda a faixa de lance atual (some sozinha).
 * A tela do OBS fica aberta o dia todo, por isso a contingência não para.
 */
export function useOverlay(token: string, initial: OverlayState, realtime: Realtime) {
    const [state, setState] = useState(initial);
    const [banner, setBanner] = useState<Banner | null>(null);
    const [connection, setConnection] = useState<ConnectionState>('connecting');
    // Diferença entre o relógio do navegador e o do servidor, medida na carga da página.
    const skew = useRef(Date.now() - initial.server_time_ms);

    useEffect(() => {
        document.documentElement.style.background = 'transparent';
        document.body.style.background = 'transparent';
    }, []);

    // Fonte própria: registra o arquivo (mesma origem do overlay) antes de usar a família; até carregar vale a fonte de reserva.
    const face = state.font_face;
    useEffect(() => {
        if (!face || typeof FontFace === 'undefined') return;

        let cancelled = false;
        const font = new FontFace(face.family, `url(${face.url}) format("${face.format === 'ttf' ? 'truetype' : face.format === 'otf' ? 'opentype' : face.format}")`);

        font.load().then((loaded) => {
            if (!cancelled) document.fonts.add(loaded);
        }).catch(() => undefined);

        return () => {
            cancelled = true;
        };
    }, [face?.family, face?.url, face?.format]); // eslint-disable-line react-hooks/exhaustive-deps

    useEffect(() => {
        let stop: (() => void) | undefined;
        let cancelled = false;

        subscribeToChannel(
            realtime,
            `overlay.${token}`,
            {
                '.overlay.updated': (payload: { state: OverlayState }) => setState(payload.state),
                '.overlay.lowerthird': (payload: { banner: Banner }) => setBanner(payload.banner),
            },
            setConnection,
        )
            .then((unsubscribe) => (cancelled ? unsubscribe() : (stop = unsubscribe)))
            .catch(() => setConnection('disconnected'));

        return () => {
            cancelled = true;
            stop?.();
        };
    }, [token]);

    useEffect(() => {
        if (connection === 'connected') {
            return;
        }

        const timer = window.setInterval(async () => {
            try {
                const response = await fetch(route('overlay.state', token), { headers: { Accept: 'application/json' }, cache: 'no-store' });
                if (response.ok) setState(await response.json());
            } catch {
                // sem rede: tenta de novo no próximo ciclo
            }
        }, POLL_MS);

        return () => window.clearInterval(timer);
    }, [connection, token]);

    useEffect(() => {
        if (!banner) {
            return;
        }

        const timer = window.setTimeout(() => setBanner(null), (state.lower_third_settings?.seconds ?? 7) * 1000);

        return () => window.clearTimeout(timer);
    }, [banner]);

    return { state, banner, connection, skew: skew.current };
}
