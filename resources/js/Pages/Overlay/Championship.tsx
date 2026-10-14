import '../../../css/obs-championship.css';
import '@/lib/artFonts';
import {
    ObsBundle,
    ObsCard,
    ObsConfig,
    ObsItem,
    ObsMatch,
    ObsScorer,
    ObsStandingRow,
    ObsTeam,
    OBS_VIEWS,
    obsConfig,
    obsFeaturedMatch,
    obsItems,
    obsKickoff,
    obsPageCount,
    obsTeam,
} from '@/lib/obsChampionship';
import { OVERLAY_FONT_KEYS, overlayFontFamily } from '@/lib/overlayVisual';
import { Realtime } from '@/lib/echo';
import { OverlayState, useOverlay } from '@/lib/overlay';
import { Head } from '@inertiajs/react';
import { CSSProperties, ReactNode, useEffect, useMemo, useRef, useState } from 'react';

const hex = (value: string, fallback: string): string => (/^#[0-9a-f]{6}$/i.test(value) ? value : fallback);

function rgba(color: string, alpha: number): string {
    const value = hex(color, '#0b0d13').slice(1);

    return `rgba(${parseInt(value.slice(0, 2), 16)},${parseInt(value.slice(2, 4), 16)},${parseInt(value.slice(4, 6), 16)},${alpha})`;
}

const TITLES: Record<string, string> = { standings: 'Classificação', fixtures: 'Próximos jogos', results: 'Últimos resultados', scorers: 'Artilharia', discipline: 'Cartões', matchcard: '', champion: 'Campeão', ticker: '' };

function Badge({ team, logos }: { team: ObsTeam; logos: boolean }) {
    return team.logo && logos ? (
        <span className="obs-badge"><img src={team.logo} alt="" /></span>
    ) : (
        <span className="obs-badge" style={{ background: hex(team.color ?? '', '#8253cd') }}>{team.short ?? ''}</span>
    );
}

function Row({ bundle, cfg, item, index }: { bundle: ObsBundle; cfg: ObsConfig; item: ObsItem; index: number }) {
    const style = { '--i': index } as CSSProperties;
    const logos = cfg.logos === 1;

    if (cfg.view === 'standings') {
        const row = item as ObsStandingRow;
        const cells = cfg.cols === 'min' ? [row.points] : cfg.cols === 'full' ? [row.played, row.won, row.drawn, row.lost, row.gd, row.points] : [row.played, row.gd, row.points];

        return (
            <div className={`obs-row ${row.position === 1 ? 'is-first' : ''}`} style={style}>
                <b className="obs-pos">{row.position}</b>
                <Badge team={obsTeam(bundle, row.team_id, row.name)} logos={logos} />
                <span className="obs-name">{row.name}</span>
                {cells.map((cell, position) => (
                    <i key={position} className={`obs-num ${position === cells.length - 1 ? 'is-points' : ''}`}>{cell}</i>
                ))}
            </div>
        );
    }

    if (cfg.view === 'fixtures' || cfg.view === 'results') {
        const match = item as ObsMatch;
        const home = obsTeam(bundle, match.home_team_id, match.home_name);
        const away = obsTeam(bundle, match.away_team_id, match.away_name);
        const kick = obsKickoff(match);
        const score = match.home_score === null || match.away_score === null ? '– × –' : `${match.home_score} × ${match.away_score}`;

        return (
            <div className="obs-row obs-match" style={style}>
                <span className="obs-name is-home">{home.name}</span>
                <Badge team={home} logos={logos} />
                {cfg.view === 'results' ? (
                    <strong className={`obs-score ${match.status === 'live' ? 'is-live' : ''}`}>{score}</strong>
                ) : (
                    <span className="obs-when"><strong>{match.status === 'live' ? 'AO VIVO' : kick.time}</strong><small>{match.status === 'live' ? score : kick.day}</small></span>
                )}
                <Badge team={away} logos={logos} />
                <span className="obs-name">{away.name}</span>
            </div>
        );
    }

    if (cfg.view === 'scorers') {
        const scorer = item as ObsScorer;

        return (
            <div className={`obs-row ${index === 0 ? 'is-first' : ''}`} style={style}>
                <b className="obs-pos">{index + 1}</b>
                <Badge team={obsTeam(bundle, scorer.team_id, scorer.team_name ?? '')} logos={logos} />
                <span className="obs-name">{scorer.name}<small>{scorer.team_name}</small></span>
                <i className="obs-num is-points">{scorer.goals}</i>
            </div>
        );
    }

    const card = item as ObsCard;

    return (
        <div className="obs-row" style={style}>
            <Badge team={obsTeam(bundle, card.team_id, card.team_name ?? '')} logos={logos} />
            <span className="obs-name">{card.name}<small>{card.team_name}</small></span>
            <i className="obs-num"><span className="obs-card is-yellow" />{card.yellow}</i>
            <i className="obs-num"><span className="obs-card is-red" />{card.red}</i>
        </div>
    );
}

function Ticker({ bundle, cfg, items }: { bundle: ObsBundle; cfg: ObsConfig; items: ObsMatch[] }) {
    const track = useRef<HTMLDivElement>(null);
    const [width, setWidth] = useState(1920);

    useEffect(() => {
        setWidth(track.current?.firstElementChild instanceof HTMLElement ? track.current.firstElementChild.scrollWidth || 1920 : 1920);
    }, [items]);

    if (items.length === 0) return <div className="obs-empty">Sem jogos para exibir</div>;

    const chips = items.map((match) => {
        const home = obsTeam(bundle, match.home_team_id, match.home_name);
        const away = obsTeam(bundle, match.away_team_id, match.away_name);
        const done = match.home_score !== null && match.away_score !== null;

        return (
            <span className="obs-chip" key={match.id}>
                <small>{match.status === 'live' ? 'AO VIVO' : done ? 'FIM' : obsKickoff(match).day}</small>
                <Badge team={home} logos={cfg.logos === 1} />
                <b>{home.short ?? home.name}</b>
                <strong>{done ? `${match.home_score} × ${match.away_score}` : obsKickoff(match).time}</strong>
                <b>{away.short ?? away.name}</b>
                <Badge team={away} logos={cfg.logos === 1} />
            </span>
        );
    });

    return (
        <div ref={track} className="obs-track" style={{ '--o-track': `${width}px`, '--o-duration': `${Math.max(8, width / cfg.speed)}s` } as CSSProperties}>
            <div>{chips}</div>
            <div aria-hidden="true">{chips}</div>
        </div>
    );
}

function Body({ bundle, cfg, page }: { bundle: ObsBundle; cfg: ObsConfig; page: number }) {
    if (cfg.view === 'matchcard') {
        const match = obsFeaturedMatch(bundle, cfg);
        if (!match) return <div className="obs-empty">Nenhuma partida</div>;
        const home = obsTeam(bundle, match.home_team_id, match.home_name);
        const away = obsTeam(bundle, match.away_team_id, match.away_name);
        const done = match.home_score !== null && match.away_score !== null;
        const kick = obsKickoff(match);

        return (
            <div className="obs-card-match">
                <div className="obs-side"><Badge team={home} logos={cfg.logos === 1} /><strong>{home.name}</strong></div>
                <div className="obs-centre">
                    <small>{match.status === 'live' ? 'AO VIVO' : done ? 'FIM DE JOGO' : kick.day}</small>
                    <b>{done ? `${match.home_score} × ${match.away_score}` : kick.time}</b>
                    <small>{match.venue ?? match.round}</small>
                </div>
                <div className="obs-side"><Badge team={away} logos={cfg.logos === 1} /><strong>{away.name}</strong></div>
            </div>
        );
    }

    if (cfg.view === 'champion') {
        const team = bundle.teams.find((item) => item.id === bundle.championship.champion_id);
        if (!team) return <div className="obs-empty">O campeão aparece quando a final termina</div>;

        return (
            <div className="obs-champion">
                <div className="obs-trophy" aria-hidden="true" />
                <Badge team={team} logos={cfg.logos === 1} />
                <strong>{team.name}</strong>
                <small>{[bundle.championship.name, bundle.championship.season].filter(Boolean).join(' · ')}</small>
            </div>
        );
    }

    if (cfg.view === 'ticker') return <Ticker bundle={bundle} cfg={cfg} items={obsItems(bundle, cfg) as ObsMatch[]} />;

    if (cfg.view === 'discipline' && bundle.cards === null) return <div className="obs-empty">Cartões não liberados pela organização</div>;

    const items = obsItems(bundle, cfg).slice(page * cfg.rows, page * cfg.rows + cfg.rows);
    if (items.length === 0) return <div className="obs-empty">Sem dados para exibir ainda</div>;

    return <>{items.map((item, index) => <Row key={`${page}-${index}`} bundle={bundle} cfg={cfg} item={item} index={index} />)}</>;
}

const FRAMES = (anim: string, pos: string): Keyframe[] => {
    switch (anim) {
        case 'drop': return [{ opacity: 0, transform: 'translateY(-48px)' }, { opacity: 1, transform: 'none' }];
        case 'slide': return [{ opacity: 0, transform: `translateX(${pos.endsWith('r') ? '' : '-'}110%)` }, { opacity: 1, transform: 'none' }];
        case 'zoom': return [{ opacity: 0, transform: 'scale(.82)' }, { opacity: 1, transform: 'none' }];
        case 'wipe': return [{ clipPath: 'inset(0 100% 0 0)', opacity: 1 }, { clipPath: 'inset(0 0 0 0)', opacity: 1 }];
        case 'fade': case 'cascade': return [{ opacity: 0 }, { opacity: 1 }];
        case 'flip': return [{ opacity: 0, transform: 'perspective(900px) rotateX(-80deg)' }, { opacity: 1, transform: 'perspective(900px) rotateX(0)' }];
        case 'bounce': return [{ opacity: 0, transform: 'translateY(90px) scale(.92)' }, { opacity: 1, transform: 'translateY(-14px) scale(1.02)', offset: 0.6 }, { opacity: 1, transform: 'none' }];
        default: return [{ opacity: 0, transform: 'translateY(48px)' }, { opacity: 1, transform: 'none' }];
    }
};

export default function Championship({ token, view, state, realtime }: { token: string; view: string; state: OverlayState; realtime: Realtime }) {
    const cfg = useMemo(() => obsConfig(typeof window === 'undefined' ? '' : window.location.search, view, OVERLAY_FONT_KEYS), [view]);

    // Sem `live=1` a tela fica sempre no ar; com `live=1` só quando o operador a mostra (comando ao vivo do canal).
    return cfg.live === 1 ? <LiveGate token={token} cfg={cfg} state={state} realtime={realtime} /> : <ChampionshipView token={token} cfg={cfg} visible />;
}

function LiveGate({ token, cfg, state: initial, realtime }: { token: string; cfg: ObsConfig; state: OverlayState; realtime: Realtime }) {
    const { state } = useOverlay(token, initial, realtime);

    return <ChampionshipView token={token} cfg={cfg} visible={state.championship?.[cfg.view] === true} />;
}

function ChampionshipView({ token, cfg, visible }: { token: string; cfg: ObsConfig; visible: boolean }) {
    const [bundle, setBundle] = useState<ObsBundle | null>(null);
    const [status, setStatus] = useState<'loading' | 'ready' | 'error' | 'empty'>('loading');
    const [page, setPage] = useState(0);
    const [scale, setScale] = useState(1);
    const panel = useRef<HTMLDivElement>(null);
    const signature = useRef('');
    const info = OBS_VIEWS.find((item) => item.key === cfg.view)!;

    // Palco lógico 1920 x 1080 ajustado à fonte de navegador do OBS.
    useEffect(() => {
        const fit = () => setScale(Math.min(window.innerWidth / 1920, window.innerHeight / 1080) || 1);
        fit();
        window.addEventListener('resize', fit);

        return () => window.removeEventListener('resize', fit);
    }, []);

    // Dados: consulta periódica da edição pedida (`edicao`/`c`/`div`) ou do jogo no ar do canal.
    useEffect(() => {
        let alive = true;
        const query = new URLSearchParams();
        for (const key of ['edicao', 'c', 'div'] as const) if (cfg[key]) query.set(key, cfg[key]);

        const load = async () => {
            try {
                const response = await fetch(`/overlay/${token}/campeonato/dados?${query.toString()}`, { cache: 'no-store' });
                if (!response.ok) throw new Error('http');
                const { data } = (await response.json()) as { data: ObsBundle | null };
                if (!alive) return;
                const next = JSON.stringify(data);
                if (next !== signature.current) {
                    signature.current = next;
                    setBundle(data);
                }
                setStatus(data === null ? 'empty' : 'ready');
            } catch {
                if (alive) setStatus('error');
            }
        };

        void load();
        const timer = window.setInterval(load, cfg.refresh * 1000);

        return () => {
            alive = false;
            window.clearInterval(timer);
        };
    }, [token, cfg]);

    const pages = bundle ? obsPageCount(bundle, cfg) : 1;

    useEffect(() => {
        if (pages <= 1) return;
        const timer = window.setInterval(() => setPage((current) => (current + 1) % pages), cfg.rotate * 1000);

        return () => window.clearInterval(timer);
    }, [pages, cfg.rotate]);

    useEffect(() => {
        if (page >= pages) setPage(0);
    }, [pages, page]);

    // Entrada (e saída/volta, se `hold` > 0) do painel pela animação escolhida. Oculto pelo operador = fora de cena.
    const ready = status !== 'loading';
    const everShown = useRef(false);
    useEffect(() => {
        const element = panel.current;
        if (!element || !ready) return;
        const reduced = typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const play = (direction: 'in' | 'out') => {
            element.animate?.(FRAMES(cfg.anim, cfg.pos), {
                duration: reduced ? 1 : direction === 'in' ? 750 : 500,
                easing: cfg.anim === 'bounce' && direction === 'in' ? 'ease-out' : 'cubic-bezier(.22,.8,.24,1)',
                fill: 'both',
                direction: direction === 'in' ? 'normal' : 'reverse',
            });
            if (cfg.anim === 'cascade' && !reduced) {
                element.querySelectorAll('.obs-row, .obs-chip').forEach((row, index) => row.animate?.([{ opacity: 0, transform: 'translateX(-40px)' }, { opacity: 1, transform: 'none' }], { duration: 480, delay: (direction === 'in' ? index : 0) * 90, easing: 'cubic-bezier(.22,.8,.24,1)', fill: 'both', direction: direction === 'in' ? 'normal' : 'reverse' }));
            }
        };
        const timers: number[] = [];

        if (!visible) {
            if (everShown.current) {
                play('out');
                timers.push(window.setTimeout(() => { element.style.visibility = 'hidden'; }, reduced ? 1 : 520));
            } else {
                element.style.visibility = 'hidden';
            }

            return () => timers.forEach((timer) => window.clearTimeout(timer));
        }

        everShown.current = true;
        element.style.visibility = 'visible';
        const cycle = () => {
            play('in');
            if (cfg.hold > 0) {
                timers.push(window.setTimeout(() => {
                    play('out');
                    timers.push(window.setTimeout(cycle, cfg.gap * 1000 + 500));
                }, cfg.hold * 1000));
            }
        };
        cycle();

        return () => timers.forEach((timer) => window.clearTimeout(timer));
    }, [ready, visible, cfg.anim, cfg.pos, cfg.hold, cfg.gap]);

    const solid = hex(cfg.bg, cfg.style === 'light' ? '#ffffff' : '#0b0d13');
    const accent = hex(cfg.accent, '#d8ad56');
    const style = {
        '--o-accent': accent,
        '--o-bg': rgba(solid, cfg.surface / 100),
        '--o-bg-solid': solid,
        ...(cfg.text ? { '--o-text': cfg.text } : {}),
        '--o-radius': `${cfg.radius}px`,
        '--o-scale': cfg.scale / 100,
        '--o-width': `${cfg.view === 'ticker' ? 1920 : cfg.width}px`,
        '--o-pad': cfg.pad / 100,
        '--o-dx': `${cfg.dx}px`,
        '--o-dy': `${cfg.dy}px`,
        ...(cfg.font !== 'global' ? { '--o-font': overlayFontFamily(cfg.font) } : {}),
        ...(cfg.bw ? { border: `${cfg.bw}px solid ${hex(cfg.bc, accent)}` } : {}),
        ...(cfg.sh ? { boxShadow: `0 ${Math.round(cfg.sh / 3)}px ${cfg.sh}px rgba(0,0,0,.6)` } : {}),
        ...(cfg.blur ? { backdropFilter: `blur(${cfg.blur}px)` } : {}),
        ...(!visible && !everShown.current ? { visibility: 'hidden' } : {}),
    } as CSSProperties;

    const heading = cfg.title || TITLES[cfg.view] || '';
    const sub = cfg.subtitle || (cfg.group ? `Grupo ${cfg.group}` : cfg.round || bundle?.championship.name || '');
    const [vertical, horizontal] = [cfg.pos[0], cfg.pos[1]];

    let content: ReactNode;
    if (status === 'error' && !bundle) content = <div className="obs-empty">Sem conexão com o campeonato</div>;
    else if (status === 'empty' || (status === 'ready' && !bundle)) content = <div className="obs-empty">Informe a edição (?edicao= ou ?c=) ou ponha um jogo no ar</div>;
    else if (bundle) content = <Body bundle={bundle} cfg={cfg} page={page} />;
    else content = null;

    return (
        <div className="obs-stage" style={{ transform: `scale(${scale})` }} data-testid="overlay-championship" data-view={cfg.view}>
            <Head title={info.label} />
            <div className={`obs-anchor is-v-${vertical} is-h-${horizontal} ${cfg.view === 'ticker' ? 'is-wide' : ''}`}>
                <div ref={panel} className={`obs-panel obs-style-${cfg.style} obs-view-${cfg.view}`} style={style} data-obs-panel>
                    {cfg.view !== 'ticker' && cfg.head === 1 && (
                        <header className="obs-head">
                            {cfg.logos === 1 && bundle?.championship.logo && <span className="obs-badge"><img src={bundle.championship.logo} alt="" /></span>}
                            <div><small>{sub}</small><strong>{heading || info.label}</strong></div>
                        </header>
                    )}
                    <div className="obs-body">{content}</div>
                    {cfg.dots === 1 && pages > 1 && (
                        <div className="obs-dots">{Array.from({ length: pages }, (_, index) => <i key={index} className={index === page ? 'on' : ''} />)}</div>
                    )}
                </div>
            </div>
        </div>
    );
}
