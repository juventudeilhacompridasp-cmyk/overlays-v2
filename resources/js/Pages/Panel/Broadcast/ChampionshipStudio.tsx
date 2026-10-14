import { Button, Field, Input, Select } from '@/Components/Panel/ui';
import {
    OBS_ANIMATIONS,
    OBS_POSITIONS,
    OBS_STYLES,
    OBS_TEMPLATES,
    OBS_VIEWS,
    ObsConfig,
    ObsView,
    obsApplyStyle,
    obsConfig,
    obsQuery,
    obsStylePayload,
    obsUrl,
} from '@/lib/obsChampionship';
import { OVERLAY_FONT_KEYS } from '@/lib/overlayVisual';
import { router } from '@inertiajs/react';
import { FormEvent, useEffect, useMemo, useRef, useState } from 'react';

export interface StyleModel {
    id: string;
    name: string;
    options: Record<string, string | number>;
}

export interface ChampionshipSeason {
    id: string;
    label: string;
    divisions: { id: string; name: string }[];
}

const FONT_LABELS: Record<string, string> = { inter: 'Inter', montserrat: 'Montserrat', oswald: 'Oswald', bebas: 'Bebas Neue', anton: 'Anton', barlow: 'Barlow Condensed', 'roboto-condensed': 'Roboto Condensed', archivo: 'Archivo Black', playfair: 'Playfair Display', marker: 'Permanent Marker' };
const STORAGE = (channelId: string) => `obs:v1:${channelId}`;
const FORMAT = 'juventude-obs-models';

function load(channelId: string, view: ObsView): ObsConfig {
    try {
        const saved = window.localStorage.getItem(STORAGE(channelId));
        if (saved) return obsConfig(saved, view, OVERLAY_FONT_KEYS);
    } catch {
        // sem armazenamento: começa do padrão
    }

    return obsConfig('', view, OVERLAY_FONT_KEYS);
}

export default function ChampionshipStudio({ channelId, base, seasons, models, modelLimit, canManage }: { channelId: string; base: string; seasons: ChampionshipSeason[]; models: StyleModel[]; modelLimit: number; canManage: boolean }) {
    const [cfg, setCfg] = useState<ObsConfig>(() => load(channelId, 'standings'));
    const [modelName, setModelName] = useState('');
    const [copied, setCopied] = useState(false);
    const [preview, setPreview] = useState('');
    const frame = useRef<HTMLDivElement>(null);
    const [scale, setScale] = useState(0.3);
    const fileInput = useRef<HTMLInputElement>(null);

    const url = useMemo(() => obsUrl(base, cfg), [base, cfg]);
    const absolute = typeof window === 'undefined' ? url : `${window.location.origin}${url}`;
    const season = seasons.find((item) => item.id === cfg.edicao);

    useEffect(() => {
        try {
            window.localStorage.setItem(STORAGE(channelId), obsQuery({ ...cfg, view: 'standings' }));
        } catch {
            // ignora
        }
    }, [cfg, channelId]);

    // A prévia usa a URL real do OBS; só recarrega depois de uma pausa para não piscar enquanto o operador ajusta.
    // A prévia ignora o controle ao vivo (senão ficaria vazia até alguém mostrar a tela).
    const previewUrl = useMemo(() => obsUrl(base, { ...cfg, live: 0 }), [base, cfg]);
    useEffect(() => {
        const timer = window.setTimeout(() => setPreview(previewUrl), 350);

        return () => window.clearTimeout(timer);
    }, [previewUrl]);

    useEffect(() => {
        const element = frame.current;
        if (!element) return;
        const observer = new ResizeObserver(() => setScale(element.clientWidth / 1920));
        observer.observe(element);
        setScale(element.clientWidth / 1920);

        return () => observer.disconnect();
    }, []);

    const set = <K extends keyof ObsConfig>(key: K, value: ObsConfig[K]) => setCfg((current) => obsConfig(obsQuery({ ...current, [key]: value }), current.view, OVERLAY_FONT_KEYS));
    const applyStyle = (options: Record<string, unknown>) => setCfg((current) => obsApplyStyle(current, options, true, OVERLAY_FONT_KEYS));
    const pickView = (view: ObsView) =>
        setCfg((current) => {
            const fresh = obsConfig('', view, OVERLAY_FONT_KEYS);

            // Mantém estilo e campeonato; posição, largura e linhas voltam ao padrão da nova tela.
            return obsConfig(obsQuery({ ...fresh, ...obsStylePayload(current), pos: fresh.pos, edicao: current.edicao, div: current.div, c: current.c } as ObsConfig), view, OVERLAY_FONT_KEYS);
        });

    const saveModel = (event: FormEvent) => {
        event.preventDefault();
        router.post(route('panel.broadcast.models.store'), { name: modelName, options: obsStylePayload(cfg) }, { preserveScroll: true, onSuccess: () => setModelName('') });
    };

    const exportModels = () => {
        const blob = new Blob([JSON.stringify({ format: FORMAT, version: 1, models: models.map(({ name, options }) => ({ name, options })) }, null, 2)], { type: 'application/json' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'modelos-overlays.json';
        link.click();
        URL.revokeObjectURL(link.href);
    };

    const importModels = async (file: File | undefined) => {
        if (!file) return;
        try {
            const parsed = JSON.parse(await file.text());
            if (parsed?.format !== FORMAT || !Array.isArray(parsed.models)) throw new Error('formato');
            router.post(route('panel.broadcast.models.import'), { models: parsed.models.slice(0, modelLimit) }, { preserveScroll: true });
        } catch {
            window.alert('Arquivo inválido. Use um arquivo exportado por esta tela.');
        }
        if (fileInput.current) fileInput.current.value = '';
    };

    const num = (key: keyof ObsConfig, label: string, min: number, max: number, step = 1) => (
        <Field label={label} htmlFor={`obs-${channelId}-${key}`}>
            <Input id={`obs-${channelId}-${key}`} type="number" min={min} max={max} step={step} value={String(cfg[key])} onChange={(event) => set(key, Number(event.target.value) as never)} />
        </Field>
    );
    const color = (key: 'accent' | 'bg' | 'text' | 'bc', label: string, fallback: string) => (
        <Field label={label} htmlFor={`obs-${channelId}-${key}`}>
            <div className="flex items-center gap-2">
                <Input id={`obs-${channelId}-${key}`} type="color" value={cfg[key] || fallback} onChange={(event) => set(key, event.target.value as never)} className="h-10" />
                {cfg[key] && <Button small variant="secondary" onClick={() => set(key, '' as never)}>Padrão</Button>}
            </div>
        </Field>
    );
    const toggle = (key: 'logos' | 'head' | 'dots' | 'live', label: string) => (
        <label className="flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" className="rounded border-gray-300 text-teal-600 focus:ring-teal-500" checked={cfg[key] === 1} onChange={(event) => set(key, (event.target.checked ? 1 : 0) as never)} />
            {label}
        </label>
    );

    return (
        <div className="space-y-4" data-testid="championship-studio">
            <div className="rounded-lg border border-gray-200 p-4">
                <div className="mb-3 font-medium text-gray-900">Tela</div>
                <div className="flex flex-wrap gap-2">
                    {OBS_VIEWS.map((view) => (
                        <Button key={view.key} small variant={cfg.view === view.key ? 'primary' : 'secondary'} aria-pressed={cfg.view === view.key} title={view.hint} onClick={() => pickView(view.key)}>
                            {view.label}
                        </Button>
                    ))}
                </div>
                <p className="mt-2 text-xs text-gray-500">{OBS_VIEWS.find((view) => view.key === cfg.view)?.hint}</p>
            </div>

            <div className="rounded-lg border border-gray-200 p-4">
                <div className="mb-3 font-medium text-gray-900">Campeonato</div>
                <div className="grid gap-3 sm:grid-cols-2">
                    <Field label="Edição" htmlFor={`obs-${channelId}-edicao`} hint="Sem escolher, a tela acompanha a edição do jogo no ar do canal.">
                        <Select id={`obs-${channelId}-edicao`} value={cfg.edicao} onChange={(event) => setCfg((current) => obsConfig(obsQuery({ ...current, edicao: event.target.value, div: '' }), current.view, OVERLAY_FONT_KEYS))}>
                            <option value="">Edição do jogo no ar</option>
                            {seasons.map((item) => <option key={item.id} value={item.id}>{item.label}</option>)}
                        </Select>
                    </Field>
                    {season && season.divisions.length > 1 && (
                        <Field label="Divisão" htmlFor={`obs-${channelId}-div`}>
                            <Select id={`obs-${channelId}-div`} value={cfg.div} onChange={(event) => set('div', event.target.value)}>
                                <option value="">Primeira divisão</option>
                                {season.divisions.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                            </Select>
                        </Field>
                    )}
                    {(cfg.view === 'standings') && <Field label="Grupo (opcional)" htmlFor={`obs-${channelId}-group`}><Input id={`obs-${channelId}-group`} maxLength={40} value={cfg.group} onChange={(event) => set('group', event.target.value)} /></Field>}
                    {(cfg.view === 'fixtures' || cfg.view === 'results' || cfg.view === 'ticker') && <Field label="Rodada (opcional)" htmlFor={`obs-${channelId}-round`} hint="Ex.: Rodada 3, Final."><Input id={`obs-${channelId}-round`} maxLength={60} value={cfg.round} onChange={(event) => set('round', event.target.value)} /></Field>}
                </div>
            </div>

            <div className="rounded-lg border border-gray-200 p-4">
                <div className="mb-1 font-medium text-gray-900">Modelos</div>
                <p className="mb-3 text-xs text-gray-500">Aplicam estilo, animação, cores e acabamento. A tela e o campeonato não mudam.</p>
                <div className="flex flex-wrap gap-2">
                    {OBS_TEMPLATES.map((template) => <Button key={template.name} small variant="secondary" onClick={() => applyStyle(template.options)}>{template.name}</Button>)}
                </div>
                {models.length > 0 && (
                    <div className="mt-3">
                        <div className="mb-1 text-xs font-medium text-gray-600">Meus modelos</div>
                        <div className="flex flex-wrap gap-2">
                            {models.map((model) => (
                                <span key={model.id} className="inline-flex items-center gap-1">
                                    <Button small variant="secondary" onClick={() => applyStyle(model.options)}>{model.name}</Button>
                                    {canManage && (
                                        <Button small variant="danger" aria-label={`Excluir o modelo ${model.name}`} onClick={() => { if (window.confirm(`Excluir o modelo “${model.name}”?`)) router.delete(route('panel.broadcast.models.destroy', model.id), { preserveScroll: true }); }}>×</Button>
                                    )}
                                </span>
                            ))}
                        </div>
                    </div>
                )}
                {canManage && (
                    <div className="mt-3 space-y-2">
                        <form onSubmit={saveModel} className="flex flex-wrap items-end gap-2" aria-label="Salvar modelo">
                            <Field label={`Salvar o estilo atual (${models.length}/${modelLimit})`} htmlFor={`obs-${channelId}-model`}>
                                <Input id={`obs-${channelId}-model`} maxLength={40} placeholder="Nome do modelo" value={modelName} onChange={(event) => setModelName(event.target.value)} />
                            </Field>
                            <Button type="submit" small disabled={modelName.trim() === ''}>Salvar modelo</Button>
                        </form>
                        <div className="flex flex-wrap gap-2">
                            <Button small variant="secondary" disabled={models.length === 0} onClick={exportModels}>Exportar modelos</Button>
                            <Button small variant="secondary" onClick={() => fileInput.current?.click()}>Importar modelos</Button>
                            <input ref={fileInput} type="file" accept="application/json,.json" className="hidden" aria-label="Arquivo de modelos" onChange={(event) => void importModels(event.target.files?.[0])} />
                        </div>
                    </div>
                )}
            </div>

            <div className="rounded-lg border border-gray-200 p-4">
                <div className="mb-3 font-medium text-gray-900">Aparência</div>
                <div className="grid gap-3 sm:grid-cols-2">
                    <Field label="Estilo" htmlFor={`obs-${channelId}-style`}>
                        <Select id={`obs-${channelId}-style`} value={cfg.style} onChange={(event) => set('style', event.target.value)}>{OBS_STYLES.map(([value, label]) => <option key={value} value={value}>{label}</option>)}</Select>
                    </Field>
                    <Field label="Animação de entrada" htmlFor={`obs-${channelId}-anim`}>
                        <Select id={`obs-${channelId}-anim`} value={cfg.anim} onChange={(event) => set('anim', event.target.value)}>{OBS_ANIMATIONS.map(([value, label]) => <option key={value} value={value}>{label}</option>)}</Select>
                    </Field>
                    <Field label="Fonte" htmlFor={`obs-${channelId}-font`}>
                        <Select id={`obs-${channelId}-font`} value={cfg.font} onChange={(event) => set('font', event.target.value)}>
                            <option value="global">Padrão do sistema</option>
                            {OVERLAY_FONT_KEYS.map((key) => <option key={key} value={key}>{FONT_LABELS[key] ?? key}</option>)}
                        </Select>
                    </Field>
                    <div>
                        <div className="mb-1 text-sm font-medium text-gray-700">Posição</div>
                        <div className="grid w-fit grid-cols-3 gap-1" role="group" aria-label="Posição na tela">
                            {OBS_POSITIONS.map(([value, label]) => (
                                <button key={value} type="button" title={label} aria-label={label} aria-pressed={cfg.pos === value} onClick={() => set('pos', value)} className={`h-8 w-8 rounded border ${cfg.pos === value ? 'border-teal-600 bg-teal-600' : 'border-gray-300 bg-gray-100 hover:bg-gray-200'}`} />
                            ))}
                        </div>
                    </div>
                    {color('accent', 'Cor de destaque', '#d8ad56')}
                    {color('bg', 'Fundo', '#0b0d13')}
                    {color('text', 'Texto', '#ffffff')}
                    {color('bc', 'Cor da borda', '#d8ad56')}
                    {num('width', 'Largura (px)', 360, 1920, 10)}
                    {num('scale', 'Escala (%)', 50, 200, 5)}
                    {num('surface', 'Opacidade do fundo (%)', 30, 100)}
                    {num('radius', 'Arredondamento', 0, 28)}
                    {num('pad', 'Espaçamento (%)', 50, 220, 5)}
                    {num('bw', 'Borda (px)', 0, 12)}
                    {num('sh', 'Sombra', 0, 80)}
                    {num('blur', 'Desfoque do fundo', 0, 30)}
                    {num('dx', 'Ajuste horizontal (px)', -1000, 1000, 10)}
                    {num('dy', 'Ajuste vertical (px)', -600, 600, 10)}
                </div>
                <div className="mt-3 flex flex-wrap gap-4">{toggle('live', 'Controle ao vivo (só aparece quando eu mostrar)')}{toggle('logos', 'Escudos')}{toggle('head', 'Cabeçalho')}{toggle('dots', 'Indicador de páginas')}</div>
            </div>

            <div className="rounded-lg border border-gray-200 p-4">
                <div className="mb-3 font-medium text-gray-900">Conteúdo e tempo</div>
                <div className="grid gap-3 sm:grid-cols-2">
                    {cfg.view !== 'matchcard' && cfg.view !== 'champion' && num('rows', cfg.view === 'ticker' ? 'Jogos na faixa' : 'Linhas por página', 1, 30)}
                    {cfg.view === 'standings' && (
                        <Field label="Colunas" htmlFor={`obs-${channelId}-cols`}>
                            <Select id={`obs-${channelId}-cols`} value={cfg.cols} onChange={(event) => set('cols', event.target.value as ObsConfig['cols'])}>
                                <option value="min">Só pontos</option><option value="std">J · SG · P</option><option value="full">Completa</option>
                            </Select>
                        </Field>
                    )}
                    {cfg.view === 'ticker' && num('speed', 'Velocidade da faixa', 20, 400, 10)}
                    {cfg.view !== 'ticker' && cfg.view !== 'matchcard' && cfg.view !== 'champion' && num('rotate', 'Segundos por página', 3, 120)}
                    {num('hold', 'Ficar no ar (s, 0 = sempre)', 0, 600)}
                    {cfg.hold > 0 && num('gap', 'Pausa até voltar (s)', 3, 900)}
                    {num('refresh', 'Atualizar dados (s)', 5, 300)}
                    <Field label="Título (opcional)" htmlFor={`obs-${channelId}-title`}><Input id={`obs-${channelId}-title`} maxLength={60} value={cfg.title} onChange={(event) => set('title', event.target.value)} /></Field>
                    <Field label="Subtítulo (opcional)" htmlFor={`obs-${channelId}-subtitle`}><Input id={`obs-${channelId}-subtitle`} maxLength={60} value={cfg.subtitle} onChange={(event) => set('subtitle', event.target.value)} /></Field>
                </div>
            </div>

            <div className="rounded-lg border border-gray-200 p-4">
                <div className="mb-2 font-medium text-gray-900">Prévia e URL do OBS</div>
                <div ref={frame} className="relative w-full overflow-hidden rounded-lg border border-gray-200" style={{ aspectRatio: '16 / 9', background: 'repeating-conic-gradient(#2a2d36 0 25%, #1c1e26 0 50%) 0 0 / 28px 28px' }}>
                    {preview && <iframe title="Prévia do overlay de campeonato" src={preview} className="absolute left-0 top-0 border-0 bg-transparent" style={{ width: 1920, height: 1080, transform: `scale(${scale})`, transformOrigin: '0 0' }} />}
                </div>
                <div className="mt-3 flex flex-wrap gap-2">
                    <input readOnly aria-label="URL da fonte de navegador" value={absolute} onFocus={(event) => event.currentTarget.select()} className="min-w-[14rem] flex-1 rounded border border-gray-300 bg-gray-50 px-3 py-2 text-xs text-gray-700" />
                    <Button small variant="secondary" onClick={async () => { try { await navigator.clipboard.writeText(absolute); setCopied(true); window.setTimeout(() => setCopied(false), 1500); } catch { window.prompt('Copie a URL:', absolute); } }}>{copied ? 'Copiado!' : 'Copiar URL'}</Button>
                    <a href={url} target="_blank" rel="noreferrer" className="inline-flex items-center rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Abrir</a>
                </div>
                <p className="mt-2 text-xs text-gray-500">No OBS: Fonte → Navegador, URL acima, 1920 × 1080, fundo transparente. A URL guarda todas as opções; é só trocar a tela para ter outra fonte.</p>
            </div>
        </div>
    );
}
