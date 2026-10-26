import { Button, Field, Input, Select } from '@/Components/Panel/ui';
import { REPORT_ANIMATIONS, REPORT_STYLES, ReportAnimation, ReportStyle } from '@/lib/report';
import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { ChampionshipSeason } from './ChampionshipStudio';

export interface ReportSettings {
    kind: 'game' | 'championship';
    division_id: string | null;
    sections: string[];
    mode: 'manual' | 'auto';
    seconds: number;
    step: number;
    anchor_ms: number;
    style: ReportStyle;
    animation: ReportAnimation;
    scale: number;
}

type Patch = (data: { report_settings?: Partial<ReportSettings>; report_command?: 'next' | 'previous' | 'reset'; screens_live?: Record<string, boolean> }) => void;

/**
 * Coluna "Relatório" da Transmissão: o relatório ilustrado da partida no ar (ou de uma divisão) vira uma sequência de
 * slides na tela `relatorio` do OBS. O operador escolhe as seções, o ritmo e o visual, liga a tela e passa os slides.
 */
export default function ReportStudio({ channelId, settings: saved, seasons, hasGame, live, canOperate, patch }: { channelId: string; settings: ReportSettings; seasons: ChampionshipSeason[]; hasGame: boolean; live: boolean; canOperate: boolean; patch: Patch }) {
    const catalog = (usePage().props as { report_sections?: Record<'game' | 'championship', { key: string; label: string }[]> }).report_sections ?? { game: [], championship: [] };
    const [settings, setSettings] = useState(saved);

    useEffect(() => setSettings(saved), [saved]);

    const options = catalog[settings.kind];
    const needsGame = settings.kind === 'game' || settings.division_id === null;
    const ready = !needsGame || hasGame;
    const { step: _step, anchor_ms: _anchor, ...sendable } = settings; // eslint-disable-line @typescript-eslint/no-unused-vars
    const toggle = (key: string) => setSettings((current) => {
        const has = current.sections.includes(key);
        const next = has ? current.sections.filter((item) => item !== key) : [...current.sections, key];

        return next.length === 0 ? current : { ...current, sections: options.map((option) => option.key).filter((option) => next.includes(option)) };
    });

    return (
        <div className="space-y-4" data-testid="report-studio">
            <div className="rounded-lg border border-gray-200 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <div className="font-medium text-gray-900">Relatório ilustrado</div>
                        <p className="text-xs text-gray-500">Cada seção vira um slide com gráficos na tela <strong>relatorio</strong>. Nomes como no portal; cartões só se o portal os mostrar.</p>
                    </div>
                    <Button small variant={live ? 'primary' : 'secondary'} disabled={!canOperate || !ready} aria-pressed={live} onClick={() => patch({ report_settings: sendable, screens_live: { report: !live } })}>{live ? 'Ocultar' : 'Mostrar'}</Button>
                </div>
                {!ready && <p className="mt-2 text-xs text-amber-700">Escolha um jogo no ar (ou uma divisão, no relatório do campeonato) para usar esta tela.</p>}
                {live && (
                    <div className="mt-3 flex items-center gap-2" role="group" aria-label="Slides">
                        <Button small variant="secondary" disabled={!canOperate} onClick={() => patch({ report_command: 'previous' })}>◀ Anterior</Button>
                        <Button small variant="secondary" disabled={!canOperate} onClick={() => patch({ report_command: 'next' })}>Próximo ▶</Button>
                        <Button small variant="secondary" disabled={!canOperate} onClick={() => patch({ report_command: 'reset' })}>Do início</Button>
                        {saved.mode === 'manual' && <span className="text-xs text-gray-500">Slide {(saved.step % Math.max(1, saved.sections.length)) + 1} de {saved.sections.length}</span>}
                    </div>
                )}
            </div>

            <div className="rounded-lg border border-gray-200 p-4">
                <div className="grid gap-3 sm:grid-cols-2">
                    <Field label="Relatório" htmlFor={`report-${channelId}-kind`}>
                        <Select id={`report-${channelId}-kind`} disabled={!canOperate} value={settings.kind} onChange={(event) => {
                            const kind = event.target.value as ReportSettings['kind'];
                            setSettings({ ...settings, kind, division_id: kind === 'game' ? null : settings.division_id, sections: catalog[kind].slice(0, 3).map((option) => option.key) });
                        }}>
                            <option value="game">Da partida no ar</option>
                            <option value="championship">Do campeonato</option>
                        </Select>
                    </Field>
                    {settings.kind === 'championship' && (
                        <Field label="Divisão" htmlFor={`report-${channelId}-division`}>
                            <Select id={`report-${channelId}-division`} disabled={!canOperate} value={settings.division_id ?? ''} onChange={(event) => setSettings({ ...settings, division_id: event.target.value || null })}>
                                <option value="">A do jogo no ar</option>
                                {seasons.flatMap((season) => season.divisions.map((division) => <option key={division.id} value={division.id}>{`${season.label} · ${division.name}`}</option>))}
                            </Select>
                        </Field>
                    )}
                </div>
                <div className="mt-3 text-sm font-medium text-gray-800">Slides (na ordem do relatório)</div>
                <div className="mt-1 flex flex-wrap gap-x-4 gap-y-1">
                    {options.map((option) => (
                        <label key={option.key} className="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" className="rounded border-gray-300 text-teal-600 focus:ring-teal-500" disabled={!canOperate} checked={settings.sections.includes(option.key)} onChange={() => toggle(option.key)} />
                            {option.label}
                        </label>
                    ))}
                </div>
            </div>

            <div className="rounded-lg border border-gray-200 p-4">
                <div className="mb-2 text-sm font-medium text-gray-800">Ritmo e visual</div>
                <div className="grid gap-3 sm:grid-cols-2">
                    <Field label="Passagem" htmlFor={`report-${channelId}-mode`}>
                        <Select id={`report-${channelId}-mode`} disabled={!canOperate} value={settings.mode} onChange={(event) => setSettings({ ...settings, mode: event.target.value as ReportSettings['mode'] })}>
                            <option value="manual">Manual (o operador passa)</option>
                            <option value="auto">Automática (em loop)</option>
                        </Select>
                    </Field>
                    <Field label={`Tempo por slide · ${settings.seconds}s`} htmlFor={`report-${channelId}-seconds`}>
                        <Input id={`report-${channelId}-seconds`} type="range" min="5" max="60" disabled={!canOperate || settings.mode === 'manual'} value={settings.seconds} onChange={(event) => setSettings({ ...settings, seconds: Number(event.target.value) })} />
                    </Field>
                    <Field label="Estilo" htmlFor={`report-${channelId}-style`}>
                        <Select id={`report-${channelId}-style`} disabled={!canOperate} value={settings.style} onChange={(event) => setSettings({ ...settings, style: event.target.value as ReportStyle })}>
                            {REPORT_STYLES.map((item) => <option key={item.key} value={item.key}>{item.label}</option>)}
                        </Select>
                    </Field>
                    <Field label="Entrada do slide" htmlFor={`report-${channelId}-animation`}>
                        <Select id={`report-${channelId}-animation`} disabled={!canOperate} value={settings.animation} onChange={(event) => setSettings({ ...settings, animation: event.target.value as ReportAnimation })}>
                            {REPORT_ANIMATIONS.map((item) => <option key={item.key} value={item.key}>{item.label}</option>)}
                        </Select>
                    </Field>
                    <Field label={`Tamanho · ${settings.scale}%`} htmlFor={`report-${channelId}-scale`}>
                        <Input id={`report-${channelId}-scale`} type="range" min="50" max="150" step="5" disabled={!canOperate} value={settings.scale} onChange={(event) => setSettings({ ...settings, scale: Number(event.target.value) })} />
                    </Field>
                </div>
                {canOperate && <Button small className="mt-3" onClick={() => patch({ report_settings: sendable })}>Aplicar relatório</Button>}
            </div>
        </div>
    );
}
