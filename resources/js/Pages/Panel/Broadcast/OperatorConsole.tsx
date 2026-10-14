import { bump, hotkeyAction, liveCount, OPERATOR_CARTELAS, OPERATOR_CHAMPIONSHIP_LABELS, OPERATOR_LAYERS, OPERATOR_NOTICES, OPERATOR_SCREENS, OperatorLayerKey, stepLabel } from '@/lib/operatorConsole';
import { OBS_VIEWS } from '@/lib/obsChampionship';
import { STATS_METRICS } from '@/lib/statsLook';
import { useOnline } from '@/lib/pwa';
import { router } from '@inertiajs/react';
import { ReactNode, useEffect, useRef, useState } from 'react';
import { defaultLayout, hidePanel, Layout, PANELS, PanelId, PRESETS, sanitizeLayout, showPanel } from '@/lib/operatorLayout';
import type { Channel, GameOption, Scene } from './Index';
import OperatorWorkspace, { Block } from './OperatorWorkspace';

const LAYOUT_KEY = 'broadcast:operator-layout:v1';
const NUMBERS = STATS_METRICS.filter((metric) => metric.key !== 'possession');

/** Botão liga/desliga: verde = no ar. */
function Tile({ on, label, hint, disabled, onClick, big = false }: { on: boolean; label: string; hint?: string; disabled?: boolean; onClick: () => void; big?: boolean }) {
    return (
        <button type="button" disabled={disabled} aria-pressed={on} onClick={onClick}
            className={`flex w-full items-center justify-between gap-1 rounded-md border px-2 text-left font-semibold leading-tight transition-colors disabled:cursor-not-allowed disabled:opacity-40 ${big ? 'h-11 text-sm [@media(max-height:800px)]:h-9' : 'h-7 text-xs [@media(max-height:800px)]:h-6'} ${on ? 'border-emerald-700 bg-emerald-600 text-white shadow-inner' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'}`}>
            <span className="truncate">{label}</span>
            <span className={`shrink-0 text-[10px] font-bold uppercase ${on ? 'text-emerald-100' : 'text-gray-400'}`}>{hint ?? (on ? 'No ar' : 'Oculto')}</span>
        </button>
    );
}

const Chip = ({ active, children, ...props }: { active?: boolean; children: ReactNode } & React.ButtonHTMLAttributes<HTMLButtonElement>) => (
    <button type="button" {...props} aria-pressed={active} className={`h-7 rounded-md border px-2 text-xs font-semibold disabled:cursor-not-allowed disabled:opacity-40 ${active ? 'border-teal-700 bg-teal-600 text-white' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'}`}>{children}</button>
);

/** Estatísticas: contador com − e +. */
function Counter({ value, label, disabled, onChange }: { value: number; label: string; disabled: boolean; onChange: (next: number) => void }) {
    return (
        <span className="inline-flex items-center overflow-hidden rounded-md border border-gray-300">
            <button type="button" aria-label={`Menos ${label}`} disabled={disabled} className="h-6 w-6 bg-gray-50 text-sm font-bold text-gray-600 hover:bg-gray-100 disabled:opacity-40" onClick={() => onChange(bump(value, -1))}>−</button>
            <span className="w-7 text-center text-xs font-bold tabular-nums text-gray-900" aria-label={label}>{value}</span>
            <button type="button" aria-label={`Mais ${label}`} disabled={disabled} className="h-6 w-6 bg-gray-50 text-sm font-bold text-gray-600 hover:bg-gray-100 disabled:opacity-40" onClick={() => onChange(bump(value, 1))}>+</button>
        </span>
    );
}

function Controls({ channel, games, scenes, canOperate, onExit, layout, editing, onLayout }: { channel: Channel; games: GameOption[]; scenes: Scene[]; canOperate: boolean; onExit: () => void; layout: Layout; editing: boolean; onLayout: (next: Layout) => void }) {
    const hasGame = channel.game_id !== null;
    const send = (data: object, rollback?: () => void) => router.patch(route('panel.broadcast.update', channel.id), data as never, { preserveScroll: true, preserveState: true, onError: rollback });

    // Cliques valem na hora na tela (otimista) e voltam ao estado real se o servidor recusar.
    const [layers, setLayers] = useState(channel.layers);
    const [screens, setScreens] = useState(channel.screens_live);
    const [champ, setChamp] = useState(channel.championship_live);
    const [custom, setCustom] = useState(Object.fromEntries(channel.custom_overlays.map((item) => [item.id, item.live])));
    const [sponsorsOn, setSponsorsOn] = useState(channel.sponsor_settings.enabled);
    const [stats, setStats] = useState({ home: channel.stats_settings.home, away: channel.stats_settings.away });
    const [title, setTitle] = useState('');
    const [person, setPerson] = useState('');
    useEffect(() => setLayers(channel.layers), [channel.layers]);
    useEffect(() => setScreens(channel.screens_live), [channel.screens_live]);
    useEffect(() => setChamp(channel.championship_live), [channel.championship_live]);
    useEffect(() => setCustom(Object.fromEntries(channel.custom_overlays.map((item) => [item.id, item.live]))), [channel.custom_overlays]);
    useEffect(() => setSponsorsOn(channel.sponsor_settings.enabled), [channel.sponsor_settings.enabled]);
    useEffect(() => setStats({ home: channel.stats_settings.home, away: channel.stats_settings.away }), [channel.stats_settings.home, channel.stats_settings.away]);

    const layerOn = (key: OperatorLayerKey) => (key === 'sponsors' ? sponsorsOn : layers[key]);
    const toggleLayer = (key: OperatorLayerKey) => {
        const meta = OPERATOR_LAYERS.find((item) => item.key === key)!;

        if (!canOperate || (meta.needsGame && !hasGame)) return;

        const next = !layerOn(key);

        if (key === 'sponsors') {
            setSponsorsOn(next);
            send({ sponsor_settings: { enabled: next } }, () => setSponsorsOn(channel.sponsor_settings.enabled));
        } else {
            setLayers({ ...layers, [key]: next });
            send({ layers: { [key]: next } }, () => setLayers(channel.layers));
        }
    };
    const lineup = (command: 'next' | 'previous' | 'reset') => canOperate && hasGame && send({ lineup_command: command });
    const toggleScreen = (key: string) => {
        const next = !screens[key];
        setScreens({ ...screens, [key]: next });
        send({ screens_live: { [key]: next } }, () => setScreens(channel.screens_live));
    };
    const setCount = (side: 'home' | 'away', key: string, value: number) => {
        const next = { ...stats, [side]: { ...stats[side], [key]: value } };
        setStats(next);
        send({ stats_settings: { home: next.home, away: next.away } }, () => setStats({ home: channel.stats_settings.home, away: channel.stats_settings.away }));
    };

    // Atalhos (1 a 5, setas, R, Esc); a referência evita registrar de novo a cada clique.
    const handler = useRef<(event: KeyboardEvent) => void>(() => undefined);
    handler.current = (event) => {
        const action = hotkeyAction(event.key, event.target as HTMLElement | null, { ctrl: event.ctrlKey, alt: event.altKey, meta: event.metaKey });

        if (!action) return;
        // Editando o layout, os atalhos da transmissão ficam travados (só Esc sai da edição).
        if (editing && action.type !== 'exit') return;
        event.preventDefault();

        if (action.type === 'exit') onExit();
        else if (action.type === 'layer') toggleLayer(action.key);
        else lineup(action.command);
    };
    useEffect(() => {
        const listener = (event: KeyboardEvent) => handler.current(event);
        window.addEventListener('keydown', listener);

        return () => window.removeEventListener('keydown', listener);
    }, []);

    const options = hasGame && !games.some((game) => game.id === channel.game_id) ? [{ id: channel.game_id as string, label: channel.game_label ?? 'Jogo atual' }, ...games] : games;
    const settings = channel.lineup_settings;
    const team = ['cards', 'individual', 'show', 'full'].includes(settings.presentation);
    const players = channel.lineup_people;
    const noGameHint = hasGame ? undefined : 'Escolha o jogo no ar';

    const blocks: Partial<Record<PanelId, Block>> = {
        live: { right: <span className="text-[10px] font-semibold text-gray-400">teclas 1–5</span>, body: (
                        <div className="grid grid-cols-2 gap-1.5">
                            {OPERATOR_LAYERS.map((layer, index) => (
                                <div key={layer.key} className={index === OPERATOR_LAYERS.length - 1 ? 'col-span-2' : ''}><Tile big on={layerOn(layer.key)} label={`${layer.hotkey} · ${layer.label}`} hint={layer.needsGame && !hasGame ? 'sem jogo' : undefined} disabled={!canOperate || (layer.needsGame && !hasGame)} onClick={() => toggleLayer(layer.key)} /></div>
                            ))}
                        </div>
        ) },
        screens: { body: (
                        <div className="grid grid-cols-2 gap-1">
                            {OPERATOR_SCREENS.map((screen) => (
                                <Tile key={screen.key} on={screens[screen.key] === true} label={screen.label} hint={screens[screen.key] === true ? 'ON' : ''} disabled={!canOperate || (screen.needsGame && !hasGame)} onClick={() => toggleScreen(screen.key)} />
                            ))}
                        </div>
        ) },
        championship: { body: (
                        <div className="grid grid-cols-2 gap-1">
                            {OBS_VIEWS.map((view) => (
                                <Tile key={view.key} on={champ[view.key] === true} label={OPERATOR_CHAMPIONSHIP_LABELS[view.key] ?? view.label} hint={champ[view.key] === true ? 'ON' : ''} disabled={!canOperate} onClick={() => { const next = !champ[view.key]; setChamp({ ...champ, [view.key]: next }); send({ championship_live: { [view.key]: next } }, () => setChamp(channel.championship_live)); }} />
                            ))}
                        </div>
        ) },
        lineup: { right: <span className="text-[10px] font-semibold text-gray-400">← → passam · R reinicia</span>, body: (
            <>
                        <div className="mb-1.5 grid grid-cols-2 gap-1.5">
                            <select aria-label="Apresentação da escalação" disabled={!canOperate} value={settings.presentation} onChange={(event) => send({ lineup_settings: { presentation: event.target.value } })} className="h-7 rounded-md border border-gray-300 bg-white py-0 pl-2 pr-7 text-xs font-semibold leading-none text-gray-800">
                                <option value="sequence">Lista → esquema</option>
                                <option value="list">Só lista</option>
                                <option value="formation">Só esquema</option>
                                <option value="cards">Cartões</option>
                                <option value="individual">Jogador a jogador</option>
                                <option value="show">Cartões + jogadores</option>
                                <option value="full">Completa</option>
                            </select>
                            <div className="grid grid-cols-2 gap-1.5">
                                <Chip active={settings.team === 'home'} disabled={!canOperate || !team} onClick={() => send({ lineup_settings: { team: 'home' } })}>Mandante</Chip>
                                <Chip active={settings.team === 'away'} disabled={!canOperate || !team} onClick={() => send({ lineup_settings: { team: 'away' } })}>Visitante</Chip>
                            </div>
                        </div>
                        <div className="grid grid-cols-[1fr_2fr_1fr] gap-1.5">
                            <button type="button" disabled={!canOperate || !hasGame || !team} onClick={() => lineup('previous')} className="h-14 rounded-md border border-gray-300 bg-white text-sm font-bold text-gray-800 hover:bg-gray-50 disabled:opacity-40">← Anterior</button>
                            <button type="button" disabled={!canOperate || !hasGame || !team} onClick={() => lineup('next')} className="h-14 rounded-md border border-teal-700 bg-teal-600 text-base font-bold text-white hover:bg-teal-700 disabled:opacity-40">Próximo →</button>
                            <button type="button" disabled={!canOperate || !hasGame || !team} onClick={() => lineup('reset')} className="h-14 rounded-md border border-gray-300 bg-white text-sm font-bold text-gray-800 hover:bg-gray-50 disabled:opacity-40">↺ Início</button>
                        </div>
                        <div className="mt-1.5 flex items-center justify-between gap-2">
                            <span className="text-[11px] font-semibold text-gray-500" data-testid="operator-step">{team ? stepLabel(settings.individual_mode, settings.step, channel.lineup_steps) : 'Apresentação sem etapas'}</span>
                            <div className="grid grid-cols-2 gap-1">
                                <Chip active={settings.individual_mode === 'manual'} disabled={!canOperate || !team} onClick={() => send({ lineup_settings: { individual_mode: 'manual' } })}>Manual</Chip>
                                <Chip active={settings.individual_mode === 'auto'} disabled={!canOperate || !team} onClick={() => send({ lineup_settings: { individual_mode: 'auto' } })}>Automático</Chip>
                            </div>
                        </div>
                        {noGameHint && <p className="mt-1 text-[11px] text-amber-700">{noGameHint}</p>}
            </>
        ) },
        notices: { right: <span className="text-[10px] font-semibold text-gray-400">{layers.lower_third ? 'lances no ar' : 'lances OCULTOS'}</span>, body: (
            <>
                        <div className="grid grid-cols-3 gap-1">
                            {OPERATOR_NOTICES.map((notice) => (
                                <button key={notice.title} type="button" disabled={!canOperate || !layers.lower_third} onClick={() => send({ lower_third_show: { kind: 'custom', title: notice.title, subtitle: notice.subtitle } })} className="h-9 rounded-md border border-amber-300 bg-amber-50 px-1 text-[11px] font-bold leading-tight text-amber-900 hover:bg-amber-100 disabled:opacity-40">{notice.title}</button>
                            ))}
                        </div>
                        <form className="mt-1.5 flex gap-1" onSubmit={(event) => { event.preventDefault(); if (title.trim() !== '' && canOperate && layers.lower_third) { send({ lower_third_show: { kind: 'custom', title: title.trim() } }); setTitle(''); } }}>
                            <input aria-label="Aviso livre" value={title} onChange={(event) => setTitle(event.target.value)} maxLength={80} placeholder="Aviso livre + Enter" className="h-7 min-w-0 flex-1 rounded-md border border-gray-300 px-2 py-0 text-xs leading-none" disabled={!canOperate} />
                            <button type="submit" disabled={!canOperate || !layers.lower_third || title.trim() === ''} className="h-7 rounded-md border border-teal-700 bg-teal-600 px-3 text-xs font-bold text-white disabled:opacity-40">Mostrar</button>
                        </form>
                        <div className="mt-1.5 flex gap-1">
                            <select aria-label="Jogador em destaque" value={person} onChange={(event) => setPerson(event.target.value)} className="h-7 min-w-0 flex-1 rounded-md border border-gray-300 bg-white py-0 pl-2 pr-7 text-xs leading-none" disabled={!canOperate || players.length === 0}>
                                <option value="">{players.length === 0 ? 'Sem escalação lançada' : 'Jogador em destaque…'}</option>
                                {players.map((item) => <option key={item.id} value={item.id}>{item.shirt ? `${item.shirt} · ` : ''}{item.name} ({item.team})</option>)}
                            </select>
                            <button type="button" disabled={!canOperate || !layers.lower_third || person === ''} onClick={() => send({ lower_third_show: { kind: 'player', person_id: person } })} className="h-7 rounded-md border border-teal-700 bg-teal-600 px-3 text-xs font-bold text-white disabled:opacity-40">Destacar</button>
                        </div>
            </>
        ) },
        cartelas: { body: (
                        <div className="grid grid-cols-4 gap-1">
                            {OPERATOR_CARTELAS.map((item) => (
                                <button key={item.label} type="button" disabled={!canOperate} onClick={() => { setScreens({ ...screens, cartela: true }); send({ screen_settings: { title: item.title, subtitle: item.subtitle, minutes: item.minutes }, screens_live: { cartela: true } }, () => setScreens(channel.screens_live)); }} className="h-9 rounded-md border border-gray-300 bg-white px-1 text-[11px] font-bold leading-tight text-gray-800 hover:bg-gray-50 disabled:opacity-40">{item.label}{item.minutes > 0 ? ` · ${item.minutes}′` : ''}</button>
                            ))}
                        </div>
        ) },
        clock: { body: (
            <>
                        <div className="grid grid-cols-9 gap-1">
                            {[0, 1, 2, 3, 4, 5, 6, 7, 8].map((minutes) => (
                                <Chip key={minutes} active={channel.stoppage === minutes} disabled={!canOperate || !hasGame} onClick={() => send({ stoppage_minutes: minutes })}>{minutes === 0 ? '—' : `+${minutes}`}</Chip>
                            ))}
                        </div>
                        <div className="mt-1.5 flex items-center gap-1">
                            <span className="mr-1 text-[11px] font-bold uppercase text-gray-500">Saque</span>
                            {([[null, 'Sem'], ['home', 'Mandante'], ['away', 'Visitante']] as const).map(([side, label]) => (
                                <Chip key={label} active={channel.screen_settings.serve === side} disabled={!canOperate || !hasGame} onClick={() => send({ screen_settings: { serve: side } })}>{label}</Chip>
                            ))}
                        </div>
            </>
        ) },
        stats: { right: <span className="text-[10px] font-semibold text-gray-400">{screens.stats ? 'no ar' : 'oculta'}</span>, body: (
                        <div className="grid grid-cols-[1fr_auto_auto] items-center gap-x-2 gap-y-1" data-testid="operator-stats">
                            <span className="text-[10px] font-bold uppercase text-gray-400">&nbsp;</span>
                            <span className="text-center text-[10px] font-bold uppercase text-gray-500">Mandante</span>
                            <span className="text-center text-[10px] font-bold uppercase text-gray-500">Visitante</span>
                            {NUMBERS.map((metric) => (
                                <div key={metric.key} className="contents">
                                    <span className="truncate text-xs font-semibold text-gray-700">{metric.label}</span>
                                    <Counter value={stats.home[metric.key] ?? 0} label={`${metric.label} do mandante`} disabled={!canOperate || !hasGame} onChange={(value) => setCount('home', metric.key, value)} />
                                    <Counter value={stats.away[metric.key] ?? 0} label={`${metric.label} do visitante`} disabled={!canOperate || !hasGame} onChange={(value) => setCount('away', metric.key, value)} />
                                </div>
                            ))}
                        </div>
        ) },
        summary: { body: (
                        <div className="flex items-center gap-x-3 whitespace-nowrap text-xs text-gray-600" data-testid="operator-summary">
                            <span><strong className="text-emerald-700">{liveCount(layers, screens, champ, custom)}</strong> telas no ar</span>
                            <span className="min-w-0 truncate">Jogo: <strong className="text-gray-900">{hasGame ? (channel.game_label ?? 'no ar') : 'nenhum'}</strong></span>
                        </div>
        ) },
        ...(channel.custom_overlays.length > 0 ? { custom: { body: (
                    <div className="flex flex-wrap gap-1" data-testid="operator-custom">
                        {channel.custom_overlays.map((item) => (
                            <Chip key={item.id} active={custom[item.id] === true} disabled={!canOperate} onClick={() => { const next = !custom[item.id]; setCustom({ ...custom, [item.id]: next }); send({ custom_live: { [item.id]: next } }, () => setCustom(Object.fromEntries(channel.custom_overlays.map((overlay) => [overlay.id, overlay.live])))); }}>{item.name}</Chip>
                        ))}
                    </div>
        ) } } : {}),
    };

    return (
        <div className="flex h-full min-h-0 flex-col gap-2">
            {/* Barra superior: jogo no ar e cenas */}
            <div className="flex shrink-0 flex-wrap items-center gap-2 rounded-lg border border-gray-200 bg-white p-2">
                <label className="flex min-w-0 flex-1 items-center gap-2 text-xs font-bold uppercase text-gray-500" style={{ flexBasis: '18rem' }}>
                    <span className="shrink-0">Jogo no ar</span>
                    <select aria-label="Jogo no ar" disabled={!canOperate} value={channel.game_id ?? ''} onChange={(event) => send({ game_id: event.target.value || null })} className="h-8 w-full min-w-0 rounded-md border border-gray-300 bg-white py-0 pl-2 pr-8 text-sm font-semibold normal-case leading-none text-gray-900">
                        <option value="">Nenhum (telas vazias)</option>
                        {options.map((game) => <option key={game.id} value={game.id}>{game.label}</option>)}
                    </select>
                </label>
                <div className="flex flex-wrap items-center gap-1" aria-label="Cenas">
                    <span className="text-[11px] font-bold uppercase text-gray-500">Cenas</span>
                    {scenes.length === 0 && <span className="text-xs text-gray-400">nenhuma salva</span>}
                    {scenes.map((scene) => (
                        <Chip key={scene.id} disabled={!canOperate} onClick={() => router.patch(route('panel.broadcast.scenes.apply', { channel: channel.id, scene: scene.id }), {}, { preserveScroll: true, preserveState: true })}>{scene.name}</Chip>
                    ))}
                </div>
            </div>

            <OperatorWorkspace layout={layout} editing={editing} onChange={onLayout} blocks={blocks} />
        </div>
    );
}

/** Modo operador: tela cheia, tudo compacto e visível ao mesmo tempo, sem rolagem (no desktop). O quadro kanban continua disponível. */
export default function OperatorConsole({ channels, games, scenes, canOperate, onExit }: { channels: Channel[]; games: GameOption[]; scenes: Scene[]; canOperate: boolean; onExit: () => void }) {
    const online = useOnline();
    const [channelId, setChannelId] = useState<string | null>(() => {
        try {
            return window.localStorage.getItem('broadcast:operator-channel');
        } catch {
            return null;
        }
    });
    const channel = channels.find((item) => item.id === channelId) ?? channels[0];
    const [layout, setLayout] = useState<Layout>(() => {
        try {
            return sanitizeLayout(JSON.parse(window.localStorage.getItem(LAYOUT_KEY) ?? 'null'));
        } catch {
            return defaultLayout();
        }
    });
    const [editing, setEditing] = useState(false);
    const [notice, setNotice] = useState<string | null>(null);
    const saveLayout = (next: Layout) => {
        setLayout(next);
        setNotice(null);

        try {
            window.localStorage.setItem(LAYOUT_KEY, JSON.stringify(next));
        } catch {
            /* sem armazenamento: vale só nesta visita */
        }
    };
    const hasCustom = (channel?.custom_overlays.length ?? 0) > 0;
    // Sem overlays livres o bloco não existe na tela (mas continua no layout salvo).
    const visible = hasCustom ? layout : layout.filter((item) => item.id !== 'custom');
    const togglePanel = (id: PanelId) => {
        if (visible.some((item) => item.id === id)) return saveLayout(hidePanel(layout, id));

        const next = showPanel(layout, id);

        if (next) saveLayout(next);
        else setNotice('Sem espaço livre: reduza ou oculte outro bloco primeiro.');
    };
    const leave = () => (editing ? setEditing(false) : onExit());

    useEffect(() => {
        const previous = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        return () => {
            document.body.style.overflow = previous;
        };
    }, []);

    const pick = (id: string) => {
        setChannelId(id);

        try {
            window.localStorage.setItem('broadcast:operator-channel', id);
        } catch {
            /* sem armazenamento: vale só nesta visita */
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex flex-col gap-2 bg-gray-100 p-2 text-gray-900" data-testid="operator-console" role="region" aria-label="Modo operador">
            <div className="flex shrink-0 items-center gap-2">
                <button type="button" onClick={onExit} className="h-8 rounded-md border border-gray-300 bg-white px-3 text-xs font-bold text-gray-700 hover:bg-gray-50">← Quadro{editing ? '' : ' (Esc)'}</button>
                <h2 className="text-sm font-black uppercase tracking-wide text-gray-800">Modo operador</h2>
                {channels.length > 1 && (
                    <div className="flex flex-wrap gap-1" role="tablist" aria-label="Canal">
                        {channels.map((item) => <Chip key={item.id} active={item.id === channel?.id} role="tab" onClick={() => pick(item.id)}>{item.name}</Chip>)}
                    </div>
                )}
                {channel && channels.length === 1 && <span className="text-xs font-semibold text-gray-500">{channel.name}</span>}
                {channel && (
                    <div className="ml-3 flex flex-wrap items-center gap-1">
                        <Chip active={editing} onClick={() => { setEditing(!editing); setNotice(null); }}>{editing ? '✓ Concluir layout' : '✥ Editar layout'}</Chip>
                        {editing && (
                            <>
                                <details className="relative">
                                    <summary className="flex h-7 cursor-pointer list-none items-center rounded-md border border-gray-300 bg-white px-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">Blocos ▾</summary>
                                    <div className="absolute left-0 z-20 mt-1 w-56 rounded-md border border-gray-200 bg-white p-1 shadow-lg">
                                        {PANELS.filter((panel) => panel.id !== 'custom' || hasCustom).map((panel) => (
                                            <label key={panel.id} className="flex cursor-pointer items-center gap-2 rounded px-2 py-1 text-xs text-gray-700 hover:bg-gray-50">
                                                <input type="checkbox" checked={visible.some((item) => item.id === panel.id)} onChange={() => togglePanel(panel.id)} />
                                                {panel.title}
                                            </label>
                                        ))}
                                    </div>
                                </details>
                                <select aria-label="Modelo de layout" value="" onChange={(event) => { const preset = PRESETS.find((item) => item.key === event.target.value); if (preset) saveLayout(preset.layout.map((item) => ({ ...item }))); }} className="h-7 rounded-md border border-gray-300 bg-white py-0 pl-2 pr-7 text-xs font-semibold leading-none text-gray-700">
                                    <option value="">Modelos…</option>
                                    {PRESETS.map((preset) => <option key={preset.key} value={preset.key}>{preset.label}</option>)}
                                </select>
                                <Chip onClick={() => saveLayout(defaultLayout())}>Restaurar padrão</Chip>
                                <span className="text-[11px] text-gray-500">Arraste o título · canto ◢ redimensiona · setas e Shift+setas</span>
                            </>
                        )}
                        {notice && <span className="text-[11px] font-semibold text-amber-700" role="alert">{notice}</span>}
                    </div>
                )}
                <span className={`ml-auto inline-flex items-center gap-1 text-xs font-bold ${online ? 'text-emerald-700' : 'text-red-600'}`}><span className={`h-2 w-2 rounded-full ${online ? 'bg-emerald-500' : 'bg-red-500'}`} />{online ? 'Conectado' : 'Sem conexão'}</span>
            </div>
            {channel ? <Controls key={channel.id} channel={channel} games={games} scenes={scenes} canOperate={canOperate} onExit={leave} layout={visible} editing={editing} onLayout={(next) => saveLayout(hasCustom ? next : [...next, ...layout.filter((item) => item.id === 'custom')])} /> : <p className="text-sm text-gray-600">Nenhum canal criado ainda.</p>}
        </div>
    );
}
