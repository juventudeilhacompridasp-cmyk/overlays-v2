import { ConnectionState, Realtime, subscribeToGame } from '@/lib/echo';
import { safeJsonLd } from '@/lib/jsonLd';
import { GameClock, formatClock, isInterval, periodLabel, shownSeconds, useGameClock } from '@/lib/clock';
import GameQrCode from '@/Components/Portal/GameQrCode';
import PushButton from '@/Components/Portal/PushButton';
import ShareButtons from '@/Components/Portal/ShareButtons';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

interface Goal {
    side: 'home' | 'away' | null;
    player: string | null;
    minute: number | null;
    period: number;
    own_goal: boolean;
}

interface Team {
    name: string;
    short_name: string | null;
    logo_url: string | null;
}

export interface Snapshot {
    id: string;
    status: string;
    status_label: string;
    finished: boolean;
    home: Team | null;
    away: Team | null;
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
    sport: string;
    score_model: string;
    quarters: { period: number; home: number; away: number }[] | null;
    sets: { home: number; away: number; winner: 'home' | 'away' | null; finished: boolean }[] | null;
    fouls: { home: number; away: number; limit: number; period: number } | null;
    timeouts: { home: number; away: number; limit: number; period: number } | null;
    goals: Goal[];
    server_time_ms: number;
}

export interface Insights {
    timeline: { type: string; side: 'home' | 'away' | null; minute: number | null; period: number; player: string | null; out: string | null; own_goal: boolean }[] | null;
    standings: { title: string; home: StandingSide | null; away: StandingSide | null } | null;
    form: { home: FormItem[]; away: FormItem[] } | null;
    head_to_head: { summary: { home_wins: number; draws: number; away_wins: number }; games: { home: string | null; away: string | null; home_score: number; away_score: number; scheduled_at: string | null; competition: string; url: string }[] } | null;
    lineup: { home: LineupSide; away: LineupSide } | null;
}

interface LineupSide {
    starter?: LineupPlayer[];
    bench?: LineupPlayer[];
}

interface LineupPlayer {
    name: string;
    shirt: number | null;
    captain: boolean;
}

interface StandingSide {
    position: number;
    points: number;
    played: number;
    of: number;
}

interface FormItem {
    result: 'V' | 'E' | 'D';
    score: string;
    opponent: string | null;
    home: boolean;
    url: string;
}

const POLL_MS = 5000;

const timelineIcon: Record<string, string> = { goal: '⚽', own_goal: '⚽', substitution: '🔁', yellow_card: '🟨', red_card: '🟥' };

function Section({ title, children, testid }: { title: string; children: React.ReactNode; testid: string }) {
    return (
        <section className="mt-8 rounded-lg bg-black/30 p-4" data-testid={testid}>
            <h2 className="mb-3 text-center text-sm font-semibold uppercase tracking-wide text-gray-400">{title}</h2>
            {children}
        </section>
    );
}

function Timeline({ items }: { items: NonNullable<Insights['timeline']> }) {
    return (
        <ul className="space-y-2 text-sm">
            {items.map((item, index) => {
                const text = (
                    <span className="text-gray-200">
                        <span aria-hidden>{timelineIcon[item.type] ?? '•'} </span>
                        {item.type === 'substitution' ? (
                            <>
                                <span className="text-emerald-400">▲ {item.player ?? '—'}</span> <span className="text-red-400">▼ {item.out ?? '—'}</span>
                            </>
                        ) : (
                            <>
                                {item.player ?? (item.type === 'goal' || item.type === 'own_goal' ? 'Gol' : '—')}
                                {item.own_goal && ' (contra)'}
                            </>
                        )}
                    </span>
                );

                return (
                    <li key={index} className="grid grid-cols-[1fr_3rem_1fr] items-center gap-2">
                        <div className="text-right">{item.side === 'home' ? text : null}</div>
                        <div className="text-center text-xs tabular-nums text-gray-500">{item.minute !== null ? `${item.minute}'` : ''}</div>
                        <div className="text-left">{item.side === 'away' ? text : null}</div>
                    </li>
                );
            })}
        </ul>
    );
}

const pill: Record<FormItem['result'], string> = { V: 'bg-emerald-600', E: 'bg-gray-500', D: 'bg-red-600' };

function FormPills({ items }: { items: FormItem[] }) {
    return (
        <div className="flex flex-wrap justify-center gap-1">
            {items.length === 0 && <span className="text-xs text-gray-500">sem jogos anteriores</span>}
            {items.map((item, index) => (
                <a key={index} href={item.url} title={`${item.home ? 'em casa' : 'fora'} contra ${item.opponent ?? '—'}: ${item.score}`} className={`flex h-7 w-7 items-center justify-center rounded text-xs font-bold text-white ${pill[item.result]}`}>
                    {item.result}
                </a>
            ))}
        </div>
    );
}

function GoalList({ goals, side }: { goals: Goal[]; side: 'home' | 'away' }) {
    const mine = goals.filter((goal) => goal.side === side);

    return (
        <ul className={`space-y-1 text-sm text-gray-300 ${side === 'home' ? 'text-right' : 'text-left'}`}>
            {mine.map((goal, index) => (
                <li key={index}>
                    ⚽ {goal.player ?? 'Gol'}
                    {goal.own_goal && ' (contra)'}
                    {goal.minute !== null && <span className="ml-1 text-gray-500">{goal.minute}'</span>}
                </li>
            ))}
        </ul>
    );
}

function LineupSideView({ side }: { side: LineupSide }) {
    const starters = side.starter ?? [];
    const rows = [...starters, ...(side.bench ?? [])];

    return (
        <ul className="space-y-1 text-sm text-gray-300">
            {rows.length === 0 && <li className="text-gray-500">Escalação não informada</li>}
            {rows.map((player, index) => (
                <li key={`${player.name}-${index}`} className="flex items-center gap-2">
                    <span className="w-7 text-right tabular-nums text-gray-500">{player.shirt ?? '—'}</span>
                    <span>{player.name}{player.captain ? ' (C)' : ''}</span>
                    {index < starters.length && <span className="ml-auto text-[10px] uppercase text-emerald-400">titular</span>}
                </li>
            ))}
        </ul>
    );
}

export default function GameLive({ snapshot: initial, realtime, push_key, insights, json_ld, share_image, youtube_embed_url, media = [] }: { snapshot: Snapshot; realtime: Realtime; push_key: string | null; insights?: Insights; json_ld?: Record<string, unknown> | null; share_image?: string | null; youtube_embed_url?: string | null; media?: { title: string | null; provider: string; embed_url: string | null; url: string; type?: string; image_url?: string | null }[] }) {
    const [snapshot, setSnapshot] = useState(initial);
    const [connection, setConnection] = useState<ConnectionState>('connecting');
    // Diferença entre o relógio do navegador e o do servidor, medida na carga da página (não nos deltas, que podem atrasar na fila).
    const skew = useRef(0);
    const [ready, setReady] = useState(false);

    useEffect(() => {
        skew.current = Date.now() - initial.server_time_ms;
        setReady(true);
    }, [initial.server_time_ms]);

    useEffect(() => {
        let cancel: (() => void) | undefined;
        let cancelled = false;

        subscribeToGame<Snapshot>(realtime, initial.id, setSnapshot, setConnection)
            .then((stop) => (cancelled ? stop() : (cancel = stop)))
            .catch(() => setConnection('disconnected'));

        return () => {
            cancelled = true;
            cancel?.();
        };
    }, [initial.id]);

    // A linha do tempo e a tabela vêm da página: quando o placar ou a situação mudam, pede só esses dados de novo.
    const first = useRef(true);
    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        router.reload({ only: ['insights'] });
    }, [snapshot.home_score, snapshot.away_score, snapshot.status]);

    // Contingência: sem WebSocket, consulta o estado a cada poucos segundos (e para quando o jogo termina).
    useEffect(() => {
        if (connection === 'connected' || snapshot.finished) {
            return;
        }

        const timer = window.setInterval(async () => {
            try {
                const response = await fetch(route('public.games.state', initial.id), { headers: { Accept: 'application/json' } });
                if (response.ok) setSnapshot(await response.json());
            } catch {
                // sem rede: tenta de novo no próximo ciclo
            }
        }, POLL_MS);

        return () => window.clearInterval(timer);
    }, [connection, snapshot.finished, initial.id]);

    const seconds = shownSeconds(snapshot.clock, useGameClock(snapshot.clock, skew.current));
    const home = snapshot.home?.name ?? 'A definir';
    const away = snapshot.away?.name ?? 'A definir';

    let clockText: string;
    if (snapshot.finished) clockText = 'Encerrado';
    else if (snapshot.sets) clockText = snapshot.sets.length > 0 && snapshot.status !== 'scheduled' ? `Set ${snapshot.sets.length} em andamento` : snapshot.status === 'scheduled' ? 'Em breve' : snapshot.status_label;
    else if (snapshot.clock.state === 'not_started') clockText = snapshot.status === 'scheduled' ? 'Em breve' : snapshot.status_label;
    else if (snapshot.clock.state === 'stopped') clockText = isInterval(snapshot.clock.period, snapshot.sport) ? 'Intervalo' : `Fim do ${periodLabel(snapshot.clock.period, snapshot.sport)}`;
    else clockText = `${formatClock(seconds)} · ${periodLabel(snapshot.clock.period, snapshot.sport)}${snapshot.clock.state === 'paused' ? ' (parado)' : ''}`;

    return (
        <div className="min-h-screen bg-gray-900 px-4 py-8 text-white">
            <Head title={`${home} x ${away}`}>
                <meta name="description" content={`${home} x ${away} — ${snapshot.competition}, ${snapshot.division}. Placar e lances ao vivo.`} />
                <link rel="canonical" href={route('public.games.show', snapshot.id)} />
                <meta property="og:type" content="website" />
                <meta property="og:title" content={`${home} ${snapshot.home_score} x ${snapshot.away_score} ${away}`} />
                <meta property="og:description" content={`${snapshot.competition} · ${snapshot.division} · ${snapshot.stage}`} />
                <meta property="og:url" content={route('public.games.show', snapshot.id)} />
                {share_image && <meta property="og:image" content={share_image} />}
                {share_image && <meta property="og:image:width" content="1200" />}
                {share_image && <meta property="og:image:height" content="630" />}
                {share_image && <meta name="twitter:card" content="summary_large_image" />}
                {json_ld && <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: safeJsonLd(json_ld) }} />}
            </Head>
            <main className="mx-auto max-w-3xl">
                <Link href={route('portal.home')} className="text-sm text-gray-400 hover:text-white">
                    ← Portal
                </Link>
                <p className="text-center text-sm text-gray-400">
                    {snapshot.competition} · {snapshot.division} · {snapshot.stage}
                </p>
                <div className="mt-6 grid grid-cols-[1fr_auto_1fr] items-center gap-4">
                    <div className="flex flex-col items-end gap-2">
                        {snapshot.home?.logo_url && <img src={snapshot.home.logo_url} alt="" className="h-16 w-16 object-contain sm:h-24 sm:w-24" />}
                        <h1 className="text-right text-xl font-bold sm:text-2xl">{home}</h1>
                    </div>
                    <div className="rounded-lg bg-black/40 px-6 py-3 text-center" data-testid="live-score">
                        <span className="text-5xl font-extrabold tabular-nums">
                            {snapshot.home_score} x {snapshot.away_score}
                        </span>
                        {snapshot.home_penalties !== null && snapshot.away_penalties !== null && (
                            <div className="text-sm text-gray-400">
                                ({snapshot.home_penalties}) pên. ({snapshot.away_penalties})
                            </div>
                        )}
                    </div>
                    <div className="flex flex-col items-start gap-2">
                        {snapshot.away?.logo_url && <img src={snapshot.away.logo_url} alt="" className="h-16 w-16 object-contain sm:h-24 sm:w-24" />}
                        <h1 className="text-left text-xl font-bold sm:text-2xl">{away}</h1>
                    </div>
                </div>
                <p className="mt-4 text-center text-lg font-semibold tabular-nums text-emerald-400" data-testid="live-clock" suppressHydrationWarning>
                    {ready || snapshot.clock.state !== 'running' ? clockText : '—'}
                </p>
                {snapshot.sets && snapshot.sets.length > 0 && (
                    <p className="mt-1 text-center text-sm tabular-nums text-gray-400" data-testid="live-sets">
                        {snapshot.sets.map((set) => `${set.home}-${set.away}${set.finished ? '' : ' (em andamento)'}`).join(' · ')}
                    </p>
                )}
                {snapshot.quarters && snapshot.quarters.length > 0 && (
                    <p className="mt-1 text-center text-sm tabular-nums text-gray-400" data-testid="live-quarters">
                        {snapshot.quarters.map((quarter) => `${periodLabel(quarter.period, snapshot.sport)}: ${quarter.home}-${quarter.away}`).join(' · ')}
                    </p>
                )}
                {snapshot.fouls && (
                    <p className="mt-1 text-center text-sm text-gray-400" data-testid="live-fouls">
                        Faltas: {snapshot.fouls.home} x {snapshot.fouls.away}
                    </p>
                )}
                {snapshot.timeouts && (
                    <p className="mt-1 text-center text-sm text-gray-400" data-testid="live-timeouts">
                        Pedidos de tempo restantes: {snapshot.timeouts.home} x {snapshot.timeouts.away}
                    </p>
                )}
                {insights?.timeline ? (
                    insights.timeline.length > 0 && (
                        <Section title="Lances do jogo" testid="game-timeline">
                            <Timeline items={insights.timeline} />
                        </Section>
                    )
                ) : (
                    <div className="mt-6 grid grid-cols-2 gap-6">
                        <GoalList goals={snapshot.goals} side="home" />
                        <GoalList goals={snapshot.goals} side="away" />
                    </div>
                )}
                {insights?.standings && (
                    <Section title={`Classificação · ${insights.standings.title}`} testid="game-standings">
                        <div className="grid grid-cols-2 gap-4 text-center text-sm">
                            {(['home', 'away'] as const).map((side) => {
                                const item = insights.standings?.[side];
                                return (
                                    <div key={side}>
                                        <div className="text-2xl font-bold tabular-nums">{item ? `${item.position}º` : '—'}</div>
                                        <div className="text-xs text-gray-400">{item ? `${item.points} pts · ${item.played} jogo(s) · de ${item.of}` : 'fora da tabela'}</div>
                                    </div>
                                );
                            })}
                        </div>
                    </Section>
                )}
                {insights?.form && (
                    <Section title="Forma recente" testid="game-form">
                        <div className="grid grid-cols-2 gap-4">
                            <FormPills items={insights.form.home} />
                            <FormPills items={insights.form.away} />
                        </div>
                    </Section>
                )}
                {insights?.head_to_head && (
                    <Section title="Confronto direto" testid="game-h2h">
                        <p className="mb-3 text-center text-sm text-gray-300">
                            {home} {insights.head_to_head.summary.home_wins} vit. · {insights.head_to_head.summary.draws} emp. · {insights.head_to_head.summary.away_wins} vit. {away}
                        </p>
                        <ul className="space-y-1 text-sm text-gray-300">
                            {insights.head_to_head.games.map((game, index) => (
                                <li key={index} className="text-center">
                                    <a href={game.url} className="hover:text-white">
                                        {game.home} {game.home_score} x {game.away_score} {game.away}
                                    </a>
                                    <span className="ml-2 text-xs text-gray-500">{game.competition}</span>
                                </li>
                            ))}
                        </ul>
                    </Section>
                )}
                {insights?.lineup && (
                    <Section title="Escalações" testid="game-lineup">
                        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <div><h3 className="mb-2 text-center font-semibold">{home}</h3><LineupSideView side={insights.lineup.home} /></div>
                            <div><h3 className="mb-2 text-center font-semibold">{away}</h3><LineupSideView side={insights.lineup.away} /></div>
                        </div>
                    </Section>
                )}
                {youtube_embed_url && (
                    <Section title="Transmissão ao vivo" testid="game-youtube">
                        <div className="aspect-video overflow-hidden rounded-lg bg-black">
                            <iframe src={youtube_embed_url} title={`Transmissão ao vivo: ${home} x ${away}`} className="h-full w-full" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowFullScreen />
                        </div>
                    </Section>
                )}
                {media.length > 0 && (
                    <Section title="Vídeos e replays" testid="game-media">
                        <div className="grid gap-4 sm:grid-cols-2">{media.map((item) => item.type === 'image' ? <a key={item.image_url ?? item.title} href={item.image_url ?? '#'} target="_blank" rel="noreferrer"><img src={item.image_url ?? undefined} alt={item.title ?? 'Foto da partida'} className="aspect-video w-full rounded-lg object-cover" /></a> : <a key={item.url} href={item.url} target="_blank" rel="noreferrer" className="rounded-lg bg-black/40 p-3 text-sm text-gray-200 hover:bg-black/60"><span className="font-semibold">{item.title ?? `Vídeo · ${item.provider}`}</span><span className="mt-1 block text-xs text-gray-500">Abrir no {item.provider}</span></a>)}</div>
                    </Section>
                )}
                {snapshot.venue && <p className="mt-8 text-center text-sm text-gray-500">{snapshot.venue}</p>}
                <div className="mt-6 flex flex-wrap items-center justify-center gap-2 text-gray-900">
                    {!snapshot.finished && <PushButton kind="game" id={snapshot.id} publicKey={push_key} label="Avisar gols deste jogo" />}
                    <ShareButtons text={`${home} ${snapshot.home_score} x ${snapshot.away_score} ${away}`} url={route('public.games.show', snapshot.id)} />
                </div>
                <section className="mt-8 flex flex-col items-center gap-3 rounded-lg bg-black/30 p-4 text-center" data-testid="game-qr">
                    <h2 className="text-sm font-semibold uppercase tracking-wide text-gray-400">Abrir no celular</h2>
                    <GameQrCode url={route('public.games.show', snapshot.id)} label={`QR code de ${home} x ${away}`} />
                    <p className="max-w-xs text-xs text-gray-500">Compartilhe este código na arquibancada ou na transmissão para abrir o placar e os lances.</p>
                </section>
                <p className="mt-6 text-center text-xs text-gray-600" data-testid="live-connection">
                    {connection === 'connected' ? '● ao vivo' : connection === 'connecting' ? '○ conectando…' : '○ atualizando a cada poucos segundos'}
                </p>
            </main>
        </div>
    );
}
