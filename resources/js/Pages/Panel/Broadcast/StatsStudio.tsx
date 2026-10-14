import { Button, Field, Input, Select } from '@/Components/Panel/ui';
import { STATS_ANIMATIONS, STATS_METRICS, STATS_STYLES, STATS_VIEWS, StatsAnimation, StatsStyle, StatsView } from '@/lib/statsLook';
import { useEffect, useState } from 'react';

const NUMBERS: { key: string; label: string }[] = STATS_METRICS.filter((metric) => metric.key !== 'possession');

type Counts = Record<string, number>;

export interface StatsSettings {
    home: Counts;
    away: Counts;
    possession: number;
    view: StatsView;
    metrics: string[];
    player: string | null;
    note: string;
    style: StatsStyle;
    animation: StatsAnimation;
    duration: number;
    scale: number;
    text_scale: number;
    x: number | null;
    y: number | null;
    expires_ms: number | null;
}

interface Person {
    id: string;
    name: string;
    shirt: number | null;
    team: string;
}

type Patch = (data: { stats_settings?: Partial<StatsSettings>; screens_live?: Record<string, boolean> }) => void;

/** Estúdio das estatísticas da partida: números lançados à mão, três visões, atleta em destaque e visual da tela. */
export default function StatsStudio({ channelId, settings: saved, people, hasGame, live, canOperate, patch }: { channelId: string; settings: StatsSettings; people: Person[]; hasGame: boolean; live: boolean; canOperate: boolean; patch: Patch }) {
    const [settings, setSettings] = useState(saved);

    useEffect(() => setSettings(saved), [saved]);

    const setCount = (side: 'home' | 'away', key: string, value: number) => setSettings((current) => ({ ...current, [side]: { ...current[side], [key]: Math.max(0, Math.min(999, value || 0)) } }));
    const toggleMetric = (key: string) => setSettings((current) => {
        const has = current.metrics.includes(key);
        if (!has && current.metrics.length >= 6) return current;

        return { ...current, metrics: has ? current.metrics.filter((item) => item !== key) : [...current.metrics, key] };
    });
    const { expires_ms: _expires, ...sendable } = settings; // eslint-disable-line @typescript-eslint/no-unused-vars

    return (
        <div className="space-y-4" data-testid="stats-studio">
            <div className="rounded-lg border border-gray-200 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <div className="font-medium text-gray-900">Estatísticas da partida</div>
                        <p className="text-xs text-gray-500">{hasGame ? 'Os números valem para o jogo no ar; trocar de jogo zera.' : 'Escolha um jogo no ar para usar esta tela.'}</p>
                    </div>
                    <Button small variant={live ? 'primary' : 'secondary'} disabled={!canOperate || !hasGame} aria-pressed={live} onClick={() => patch({ stats_settings: sendable, screens_live: { stats: !live } })}>{live ? 'Ocultar' : 'Mostrar'}</Button>
                </div>
                <div className="mt-3 grid gap-2 sm:grid-cols-3" role="group" aria-label="Visão">
                    {STATS_VIEWS.map((view) => (
                        <button key={view.key} type="button" disabled={!canOperate} aria-pressed={settings.view === view.key} onClick={() => setSettings({ ...settings, view: view.key })}
                            className={`rounded-lg border p-2 text-left text-sm ${settings.view === view.key ? 'border-teal-600 bg-teal-50 text-teal-900' : 'border-gray-200 hover:bg-gray-50'}`}>
                            <div className="font-semibold">{view.label}</div>
                            <div className="text-xs text-gray-500">{view.hint}</div>
                        </button>
                    ))}
                </div>
            </div>

            {settings.view === 'comparative' && (
                <div className="rounded-lg border border-gray-200 p-4">
                    <div className="mb-2 text-sm font-medium text-gray-800">Números (mandante × visitante)</div>
                    <div className="grid grid-cols-[1fr_5rem_5rem] items-center gap-2 text-sm">
                        <span className="text-xs font-semibold uppercase text-gray-500">Indicador</span>
                        <span className="text-center text-xs font-semibold uppercase text-gray-500">Mandante</span>
                        <span className="text-center text-xs font-semibold uppercase text-gray-500">Visitante</span>
                        {NUMBERS.map((item) => (
                            <div key={item.key} className="contents">
                                <label className="text-gray-700" htmlFor={`stat-${channelId}-home-${item.key}`}>{item.label}</label>
                                <Input id={`stat-${channelId}-home-${item.key}`} aria-label={`${item.label} do mandante`} type="number" min="0" max="999" disabled={!canOperate} value={settings.home[item.key] ?? 0} onChange={(event) => setCount('home', item.key, Number(event.target.value))} />
                                <Input aria-label={`${item.label} do visitante`} type="number" min="0" max="999" disabled={!canOperate} value={settings.away[item.key] ?? 0} onChange={(event) => setCount('away', item.key, Number(event.target.value))} />
                            </div>
                        ))}
                    </div>
                    <Field label={`Posse de bola · mandante ${settings.possession}% × visitante ${100 - settings.possession}%`} htmlFor={`stat-${channelId}-possession`}>
                        <Input id={`stat-${channelId}-possession`} type="range" min="0" max="100" disabled={!canOperate} value={settings.possession} onChange={(event) => setSettings({ ...settings, possession: Number(event.target.value) })} />
                    </Field>
                    <div className="mt-3 text-sm font-medium text-gray-800">Indicadores na tela (até 6)</div>
                    <div className="mt-1 flex flex-wrap gap-x-4 gap-y-1">
                        {STATS_METRICS.map((metric) => (
                            <label key={metric.key} className="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" className="rounded border-gray-300 text-teal-600 focus:ring-teal-500" disabled={!canOperate} checked={settings.metrics.includes(metric.key)} onChange={() => toggleMetric(metric.key)} />
                                {metric.label}
                            </label>
                        ))}
                    </div>
                </div>
            )}

            {settings.view === 'player' && (
                <div className="rounded-lg border border-gray-200 p-4">
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Field label="Atleta" htmlFor={`stat-${channelId}-player`}>
                            <Select id={`stat-${channelId}-player`} disabled={!canOperate || !hasGame} value={settings.player ?? ''} onChange={(event) => setSettings({ ...settings, player: event.target.value || null })}>
                                <option value="">Escolha…</option>
                                {people.map((person) => <option key={person.id} value={person.id}>{`${person.shirt ?? '–'} · ${person.name} (${person.team})`}</option>)}
                            </Select>
                        </Field>
                        <Field label="Observação (opcional)" htmlFor={`stat-${channelId}-note`}>
                            <Input id={`stat-${channelId}-note`} maxLength={120} disabled={!canOperate} value={settings.note} onChange={(event) => setSettings({ ...settings, note: event.target.value })} placeholder="Ex.: Artilheiro do campeonato" />
                        </Field>
                    </div>
                    <p className="mt-2 text-xs text-gray-500">A foto só aparece se o atleta estiver habilitado na coluna Escalação. Gols e cartões vêm da súmula (cartões só se o portal os liberar).</p>
                </div>
            )}

            <div className="rounded-lg border border-gray-200 p-4">
                <div className="mb-2 text-sm font-medium text-gray-800">Visual</div>
                <div className="grid gap-3 sm:grid-cols-2">
                    <Field label="Estilo" htmlFor={`stat-${channelId}-style`}>
                        <Select id={`stat-${channelId}-style`} disabled={!canOperate} value={settings.style} onChange={(event) => setSettings({ ...settings, style: event.target.value as StatsStyle })}>
                            {STATS_STYLES.map((item) => <option key={item.key} value={item.key}>{item.label}</option>)}
                        </Select>
                    </Field>
                    <Field label="Animação" htmlFor={`stat-${channelId}-animation`}>
                        <Select id={`stat-${channelId}-animation`} disabled={!canOperate} value={settings.animation} onChange={(event) => setSettings({ ...settings, animation: event.target.value as StatsAnimation })}>
                            {STATS_ANIMATIONS.map((item) => <option key={item.key} value={item.key}>{item.label}</option>)}
                        </Select>
                    </Field>
                    <Field label={settings.duration === 0 ? 'Tempo no ar · até ocultar' : `Tempo no ar · ${settings.duration}s`} htmlFor={`stat-${channelId}-duration`}>
                        <Input id={`stat-${channelId}-duration`} type="range" min="0" max="120" disabled={!canOperate} value={settings.duration} onChange={(event) => setSettings({ ...settings, duration: Number(event.target.value) })} />
                    </Field>
                    <Field label={`Tamanho · ${settings.scale}%`} htmlFor={`stat-${channelId}-scale`}>
                        <Input id={`stat-${channelId}-scale`} type="range" min="50" max="200" step="5" disabled={!canOperate} value={settings.scale} onChange={(event) => setSettings({ ...settings, scale: Number(event.target.value) })} />
                    </Field>
                    <Field label={`Texto · ${settings.text_scale}%`} htmlFor={`stat-${channelId}-text`}>
                        <Input id={`stat-${channelId}-text`} type="range" min="50" max="200" step="5" disabled={!canOperate} value={settings.text_scale} onChange={(event) => setSettings({ ...settings, text_scale: Number(event.target.value) })} />
                    </Field>
                    <label className="flex items-center gap-2 self-end pb-2 text-sm text-gray-700">
                        <input type="checkbox" className="rounded border-gray-300 text-teal-600 focus:ring-teal-500" disabled={!canOperate} checked={settings.x !== null && settings.y !== null} onChange={(event) => setSettings({ ...settings, x: event.target.checked ? 30 : null, y: event.target.checked ? 56 : null })} />
                        Posição livre (X e Y)
                    </label>
                    {settings.x !== null && settings.y !== null && (
                        <>
                            <Field label={`Horizontal · ${settings.x}%`} htmlFor={`stat-${channelId}-x`}>
                                <Input id={`stat-${channelId}-x`} type="range" min="0" max="100" disabled={!canOperate} value={settings.x} onChange={(event) => setSettings({ ...settings, x: Number(event.target.value) })} />
                            </Field>
                            <Field label={`Vertical · ${settings.y}%`} htmlFor={`stat-${channelId}-y`}>
                                <Input id={`stat-${channelId}-y`} type="range" min="0" max="100" disabled={!canOperate} value={settings.y} onChange={(event) => setSettings({ ...settings, y: Number(event.target.value) })} />
                            </Field>
                        </>
                    )}
                </div>
                {canOperate && <Button small className="mt-3" disabled={!hasGame} onClick={() => patch({ stats_settings: sendable })}>Aplicar estatísticas</Button>}
            </div>
        </div>
    );
}
