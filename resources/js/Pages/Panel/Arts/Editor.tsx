import { Badge, Button, Card, Field, Input, PageHeader, Select } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { ensureFonts } from '@/lib/artFonts';
import { ArtData, ArtStyle, buildArt } from '@/lib/arts';
import { drawArt, loadImages } from '@/lib/artsDraw';
import type { RequestPayload } from '@inertiajs/core';
import { Head, Link, router } from '@inertiajs/react';
import { ReactNode, useEffect, useMemo, useRef, useState } from 'react';

type Option = { value: string; label: string };
type Format = { key: string; width: number; height: number };
type Preset = { key: string; name: string; format: string; style: ArtStyle };
type Asset = { id: string; kind: string; name: string; url: string; width: number; height: number };
type Template = { id: string; name: string; format: string; width: number; height: number; style: ArtStyle };

interface Props {
    game: { id: string; home: string; away: string; scheduled_at: string | null; status_label: string; competition: string };
    kind: 'preview' | 'result' | 'lineup' | 'round' | 'cover';
    kinds: Option[];
    data: ArtData;
    initial: { template_id: string | null; preset: string | null; name: string; format: Format; style: ArtStyle };
    templates: Template[];
    presets: Preset[];
    formats: (Option & { width: number; height: number })[];
    options: { fonts: Option[]; variants: Option[]; patterns: Option[]; crest_shapes: Option[]; weights: Option[]; elements: Option[]; platforms: Option[]; limits: { min: number; max: number } };
    can_save: boolean;
    assets: Asset[];
    asset_kinds: Option[];
}

const PREVIEW_MAX = 900;

const slug = (value: string) => value.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');

function Color({ label, value, onChange }: { label: string; value: string; onChange: (value: string) => void }) {
    return (
        <label className="flex items-center justify-between gap-2 text-sm text-gray-700">
            {label}
            <span className="flex items-center gap-2">
                <input type="color" value={value.length === 4 ? `#${value[1]}${value[1]}${value[2]}${value[2]}${value[3]}${value[3]}` : value} onChange={(event) => onChange(event.target.value)} className="h-8 w-10 cursor-pointer rounded border border-gray-300 bg-transparent p-0" />
                <code className="w-16 text-xs text-gray-500">{value}</code>
            </span>
        </label>
    );
}

function Range({ label, value, min, max, step, onChange, suffix = '' }: { label: string; value: number; min: number; max: number; step: number; onChange: (value: number) => void; suffix?: string }) {
    return (
        <label className="block text-sm text-gray-700">
            <span className="flex justify-between">
                {label}
                <span className="text-xs text-gray-500">
                    {Math.round(value * 100) / 100}
                    {suffix}
                </span>
            </span>
            <input type="range" min={min} max={max} step={step} value={value} onChange={(event) => onChange(Number(event.target.value))} className="w-full accent-emerald-600" />
        </label>
    );
}

function Check({ label, checked, onChange }: { label: string; checked: boolean; onChange: (value: boolean) => void }) {
    return (
        <label className="flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" checked={checked} onChange={(event) => onChange(event.target.checked)} className="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500" />
            {label}
        </label>
    );
}

function Pick({ label, value, options, onChange }: { label: string; value: string; options: Option[]; onChange: (value: string) => void }) {
    return (
        <Field label={label}>
            <Select value={value} onChange={(event) => onChange(event.target.value)}>
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </Select>
        </Field>
    );
}

function Section({ title, children, open = false }: { title: string; children: ReactNode; open?: boolean }) {
    return (
        <details open={open} className="group rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
            <summary className="cursor-pointer select-none px-4 py-3 text-sm font-semibold text-gray-900">{title}</summary>
            <div className="space-y-3 border-t border-gray-100 p-4">{children}</div>
        </details>
    );
}

/** Escolhe (ou envia) uma imagem da organização: fundo, logotipo ou selo. */
function AssetPick({ label, kind, assets, value, canUpload, onChange }: { label: string; kind: string; assets: Asset[]; value: string | null; canUpload: boolean; onChange: (value: string | null) => void }) {
    const own = assets.filter((asset) => asset.kind === kind);
    const [sending, setSending] = useState(false);

    const upload = (file: File | undefined) => {
        if (!file) return;
        setSending(true);
        router.post(route('panel.arts.assets.store'), { file, kind, name: file.name.replace(/\.[^.]+$/, '') } as unknown as RequestPayload, {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            only: ['assets', 'flash', 'errors'],
            onFinish: () => setSending(false),
        });
    };

    return (
        <div className="space-y-1.5">
            <Pick label={label} value={value ?? ''} options={[{ value: '', label: kind === 'logo' ? 'Logotipo da organização' : 'Nenhuma' }, ...own.map((asset) => ({ value: asset.id, label: asset.name }))]} onChange={(next) => onChange(next === '' ? null : next)} />
            {canUpload && (
                <label className="block text-xs text-gray-500">
                    {sending ? 'Enviando…' : 'Enviar nova imagem (JPG, PNG ou WebP, até 10 MB)'}
                    <input type="file" accept="image/jpeg,image/png,image/webp" disabled={sending} onChange={(event) => upload(event.target.files?.[0])} className="mt-1 block w-full text-xs" />
                </label>
            )}
        </div>
    );
}

export default function Editor({ game, kind, kinds, data, initial, templates, presets, formats, options, can_save, assets, asset_kinds }: Props) {
    const [style, setStyle] = useState<ArtStyle>(initial.style);
    const [format, setFormat] = useState<Format>(initial.format);
    const [name, setName] = useState(initial.name);
    const [templateId, setTemplateId] = useState<string | null>(initial.template_id);
    const [busy, setBusy] = useState(false);
    const canvas = useRef<HTMLCanvasElement>(null);
    const images = useRef(new Map<string, HTMLImageElement>());

    const set = <G extends keyof ArtStyle, K extends keyof ArtStyle[G]>(group: G, key: K, value: ArtStyle[G][K]) =>
        setStyle((previous) => ({ ...previous, [group]: { ...previous[group], [key]: value } }));

    // Trocar de modelo (ou salvar um novo) recarrega a página com outro estado inicial: o componente continua montado, então ressincroniza.
    useEffect(() => {
        setStyle(initial.style);
        setFormat(initial.format);
        setName(initial.name);
        setTemplateId(initial.template_id);
    }, [initial.template_id, initial.preset]); // eslint-disable-line react-hooks/exhaustive-deps

    const scale = Math.min(1, PREVIEW_MAX / Math.max(format.width, format.height));
    const previewW = Math.round(format.width * scale);
    const previewH = Math.round(format.height * scale);
    const assetUrls = useMemo(() => Object.fromEntries(assets.map((asset) => [asset.id, asset.url])), [assets]);
    const ops = useMemo(() => buildArt(style, data, previewW, previewH, assetUrls), [style, data, previewW, previewH, assetUrls]);

    useEffect(() => {
        let cancelled = false;

        Promise.all([ensureFonts(style), loadImages(ops, images.current)]).then(() => {
            if (!cancelled && canvas.current) drawArt(canvas.current, ops, style, images.current);
        });

        return () => {
            cancelled = true;
        };
    }, [ops, style]);

    const changeKind = (next: string, scope = data.scope) => router.reload({ data: { game: game.id, kind: next, scope }, only: ['data', 'kind'] });

    const changeFormat = (key: string) => {
        const preset = formats.find((item) => item.value === key);
        setFormat(preset ? { key, width: preset.width, height: preset.height } : { key: 'custom', width: format.width, height: format.height });
    };

    const download = async () => {
        setBusy(true);
        try {
            const full = document.createElement('canvas');
            full.width = format.width;
            full.height = format.height;
            const fullOps = buildArt(style, data, format.width, format.height, assetUrls);
            await Promise.all([ensureFonts(style), loadImages(fullOps, images.current)]);
            drawArt(full, fullOps, style, images.current);
            const blob = await new Promise<Blob | null>((resolve) => full.toBlob(resolve, 'image/png'));
            if (!blob) return;
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `${slug(`${game.home}-x-${game.away}`)}-${kind}-${format.width}x${format.height}.png`;
            link.click();
            URL.revokeObjectURL(link.href);
        } finally {
            setBusy(false);
        }
    };

    const payload = { name, format: format.key, width: format.width, height: format.height, style, game: game.id } as unknown as RequestPayload;

    const applyPreset = (key: string) => {
        const preset = presets.find((item) => item.key === key);
        if (!preset) return;
        setStyle(preset.style);
        setFormat(formats.find((item) => item.value === preset.format) ? { key: preset.format, ...(formats.find((item) => item.value === preset.format) as { width: number; height: number }) } : format);
        setName(preset.name);
        setTemplateId(null);
    };

    const applyTemplate = (id: string) => {
        router.get(route('panel.arts.editor'), { game: game.id, kind, template: id });
    };

    const hex = (value: string) => value;

    return (
        <PanelLayout>
            <Head title="Editor de arte" />
            <PageHeader
                title="Criar arte"
                subtitle={`${game.home} × ${game.away} · ${game.competition}${game.scheduled_at ? ` · ${game.scheduled_at}` : ''}`}
                actions={
                    <Link href={route('panel.arts.index')} className="text-sm text-gray-600 hover:text-emerald-700">
                        ← Outras partidas
                    </Link>
                }
            />

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px]">
                <div className="order-1 lg:order-2">
                    <div className="space-y-3 lg:sticky lg:top-4">
                        <div className="flex flex-wrap gap-2">
                            {kinds.map((item) => (
                                <button
                                    key={item.value}
                                    type="button"
                                    onClick={() => changeKind(item.value)}
                                    className={`rounded-full px-3 py-1 text-sm font-medium ${kind === item.value ? 'bg-emerald-600 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50'}`}
                                >
                                    {item.label}
                                </button>
                            ))}
                        </div>
                        <div className="overflow-hidden rounded-lg bg-[repeating-conic-gradient(#e5e7eb_0%_25%,#f9fafb_0%_50%)] bg-[length:16px_16px] p-3 ring-1 ring-gray-200">
                            <canvas ref={canvas} width={previewW} height={previewH} style={{ width: '100%', aspectRatio: `${format.width} / ${format.height}`, maxHeight: '75vh', objectFit: 'contain' }} className="mx-auto block bg-white shadow" aria-label="Prévia da arte" />
                        </div>
                        <div className="flex flex-wrap items-center gap-3">
                            <Button onClick={download} disabled={busy}>
                                {busy ? 'Gerando…' : 'Baixar PNG'}
                            </Button>
                            <span className="text-xs text-gray-500">
                                {format.width} × {format.height} px
                            </span>
                        </div>
                        {kind === 'round' && (
                            <div className="flex flex-wrap items-center gap-2 text-sm text-gray-700">
                                Jogos:
                                {(['day', 'round'] as const).map((scope) => (
                                    <button key={scope} type="button" onClick={() => changeKind('round', scope)} className={`rounded-full px-3 py-1 ${data.scope === scope ? 'bg-emerald-600 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50'}`}>
                                        {scope === 'day' ? 'Do mesmo dia' : 'Da mesma rodada'}
                                    </button>
                                ))}
                                <span className="text-xs text-gray-500">{data.games.length} jogo(s), no máximo 8</span>
                            </div>
                        )}
                        {kind === 'lineup' && data.lineup === null && <p className="text-sm text-amber-700">Esta partida ainda não tem escalação lançada.</p>}
                    </div>
                </div>

                <div className="order-2 space-y-3 lg:order-1">
                    <Section title="Modelo" open>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <Pick label="Modelo pronto" value="" options={[{ value: '', label: 'Aplicar…' }, ...presets.map((preset) => ({ value: preset.key, label: preset.name }))]} onChange={(key) => key && applyPreset(key)} />
                            <Pick label="Meus modelos" value={templateId ?? ''} options={[{ value: '', label: templates.length ? 'Escolher…' : 'Nenhum salvo' }, ...templates.map((template) => ({ value: template.id, label: template.name }))]} onChange={(id) => id && applyTemplate(id)} />
                        </div>
                        {can_save ? (
                            <div className="space-y-2">
                                <Field label="Nome do modelo">
                                    <Input value={name} maxLength={80} onChange={(event) => setName(event.target.value)} />
                                </Field>
                                <div className="flex flex-wrap gap-2">
                                    {templateId && (
                                        <Button small variant="secondary" onClick={() => router.patch(route('panel.arts.templates.update', templateId), payload, { preserveScroll: true })}>
                                            Atualizar modelo
                                        </Button>
                                    )}
                                    <Button small variant="secondary" disabled={name.trim() === ''} onClick={() => router.post(route('panel.arts.templates.store'), payload)}>
                                        Salvar como novo
                                    </Button>
                                    {templateId && (
                                        <Button
                                            small
                                            variant="danger"
                                            onClick={() => {
                                                if (window.confirm('Excluir este modelo?')) router.delete(route('panel.arts.templates.destroy', templateId), { data: { game: game.id } });
                                            }}
                                        >
                                            Excluir
                                        </Button>
                                    )}
                                </div>
                            </div>
                        ) : (
                            <p className="text-xs text-gray-500">Você pode gerar e baixar a arte. Salvar modelos é com quem gerencia competições.</p>
                        )}
                    </Section>

                    <Section title="Formato">
                        <Pick label="Proporção" value={format.key} options={[...formats.map((item) => ({ value: item.value, label: `${item.label} · ${item.width}×${item.height}` })), { value: 'custom', label: 'Personalizado' }]} onChange={changeFormat} />
                        {format.key === 'custom' && (
                            <div className="grid grid-cols-2 gap-3">
                                <Field label="Largura (px)">
                                    <Input type="number" min={options.limits.min} max={options.limits.max} value={format.width} onChange={(event) => setFormat({ key: 'custom', width: Math.max(options.limits.min, Math.min(options.limits.max, Number(event.target.value) || options.limits.min)), height: format.height })} />
                                </Field>
                                <Field label="Altura (px)">
                                    <Input type="number" min={options.limits.min} max={options.limits.max} value={format.height} onChange={(event) => setFormat({ key: 'custom', width: format.width, height: Math.max(options.limits.min, Math.min(options.limits.max, Number(event.target.value) || options.limits.min)) })} />
                                </Field>
                            </div>
                        )}
                    </Section>

                    <Section title="Textos">
                        <Field label="Nome da competição na arte" hint="Vazio usa o nome cadastrado (ex.: use “Cajati” em vez do nome completo).">
                            <Input value={style.text.title} maxLength={60} onChange={(event) => set('text', 'title', event.target.value)} />
                        </Field>
                        <Field label="Subtítulo" hint="Ex.: Quartas de final.">
                            <Input value={style.text.subtitle} maxLength={80} onChange={(event) => set('text', 'subtitle', event.target.value)} />
                        </Field>
                        <Field label="Título da arte" hint="Vazio usa o padrão do tipo (ex.: Jogo do dia).">
                            <Input value={style.text.headline} maxLength={80} onChange={(event) => set('text', 'headline', event.target.value)} />
                        </Field>
                        <Field label="Crédito" hint="Aparece na capa. Ex.: Narração: Fulano.">
                            <Input value={style.text.credit} maxLength={120} onChange={(event) => set('text', 'credit', event.target.value)} />
                        </Field>
                        <Field label="Rodapé">
                            <Input value={style.text.footer} maxLength={120} onChange={(event) => set('text', 'footer', event.target.value)} />
                        </Field>
                        <Field label="Hashtag">
                            <Input value={style.text.hashtag} maxLength={40} onChange={(event) => set('text', 'hashtag', event.target.value)} />
                        </Field>
                    </Section>

                    <Section title="Cores">
                        <Color label="Principal (lado da casa)" value={hex(style.palette.primary)} onChange={(value) => set('palette', 'primary', value)} />
                        <Color label="Secundária (lado visitante)" value={hex(style.palette.secondary)} onChange={(value) => set('palette', 'secondary', value)} />
                        <Color label="Texto" value={hex(style.palette.text)} onChange={(value) => set('palette', 'text', value)} />
                        <Color label="Texto suave" value={hex(style.palette.muted)} onChange={(value) => set('palette', 'muted', value)} />
                        <Color label="Destaque" value={hex(style.palette.accent)} onChange={(value) => set('palette', 'accent', value)} />
                        <Check label="Usar as cores das equipes (quando cadastradas)" checked={style.crest.use_team_colors} onChange={(value) => set('crest', 'use_team_colors', value)} />
                    </Section>

                    <Section title="Fundo">
                        <Pick label="Tipo" value={style.background.mode} options={[{ value: 'solid', label: 'Cor sólida' }, { value: 'gradient', label: 'Degradê' }]} onChange={(value) => set('background', 'mode', value as ArtStyle['background']['mode'])} />
                        <Color label="Cor" value={style.background.color} onChange={(value) => set('background', 'color', value)} />
                        {style.background.mode === 'gradient' && (
                            <>
                                <Color label="Segunda cor" value={style.background.color2} onChange={(value) => set('background', 'color2', value)} />
                                <Range label="Ângulo" value={style.background.angle} min={0} max={360} step={5} suffix="°" onChange={(value) => set('background', 'angle', value)} />
                            </>
                        )}
                        <Pick label="Padrão" value={style.background.pattern} options={options.patterns} onChange={(value) => set('background', 'pattern', value as ArtStyle['background']['pattern'])} />
                        {style.background.pattern !== 'none' && <Range label="Força do padrão" value={style.background.pattern_opacity} min={0} max={0.4} step={0.01} onChange={(value) => set('background', 'pattern_opacity', value)} />}
                        <AssetPick label="Foto de fundo" kind="background" assets={assets} value={style.background.image} canUpload={can_save} onChange={(value) => set('background', 'image', value)} />
                        {style.background.image && (
                            <>
                                <Pick label="Encaixe da foto" value={style.background.image_fit} options={[{ value: 'cover', label: 'Preencher (corta)' }, { value: 'contain', label: 'Mostrar inteira' }]} onChange={(value) => set('background', 'image_fit', value as ArtStyle['background']['image_fit'])} />
                                <Range label="Opacidade da foto" value={style.background.image_opacity} min={0.1} max={1} step={0.05} onChange={(value) => set('background', 'image_opacity', value)} />
                            </>
                        )}
                        <Color label="Película de cor" value={style.background.tint} onChange={(value) => set('background', 'tint', value)} />
                        <Range label="Força da película" value={style.background.tint_opacity} min={0} max={0.95} step={0.05} onChange={(value) => set('background', 'tint_opacity', value)} />
                        <Range label="Sombra na parte de baixo" value={style.background.shade} min={0} max={0.9} step={0.05} onChange={(value) => set('background', 'shade', value)} />
                    </Section>

                    <Section title="Logotipo">
                        <AssetPick label="Logotipo" kind="logo" assets={assets} value={style.logo.asset} canUpload={can_save} onChange={(value) => set('logo', 'asset', value)} />
                        <Range label="Tamanho do logotipo" value={style.logo.size} min={4} max={20} step={1} onChange={(value) => set('logo', 'size', value)} />
                    </Section>

                    <Section title="Tipografia">
                        <Pick label="Fonte dos títulos" value={style.type.heading} options={options.fonts} onChange={(value) => set('type', 'heading', value as ArtStyle['type']['heading'])} />
                        <Pick label="Fonte do texto" value={style.type.body} options={options.fonts} onChange={(value) => set('type', 'body', value as ArtStyle['type']['body'])} />
                        <Pick label="Peso dos títulos" value={style.type.weight} options={options.weights} onChange={(value) => set('type', 'weight', value as ArtStyle['type']['weight'])} />
                        <Range label="Tamanho do texto" value={style.type.scale} min={0.6} max={1.6} step={0.05} suffix="×" onChange={(value) => set('type', 'scale', value)} />
                        <Range label="Espaço entre letras" value={style.type.spacing} min={-0.05} max={0.3} step={0.01} onChange={(value) => set('type', 'spacing', value)} />
                        <Check label="Títulos em caixa alta" checked={style.type.uppercase} onChange={(value) => set('type', 'uppercase', value)} />
                    </Section>

                    <Section title="Layout">
                        <Pick label="Estilo" value={style.layout.variant} options={options.variants} onChange={(value) => set('layout', 'variant', value as ArtStyle['layout']['variant'])} />
                        <Pick label="Alinhamento do cabeçalho" value={style.layout.align} options={[{ value: 'center', label: 'Centralizado' }, { value: 'left', label: 'À esquerda' }]} onChange={(value) => set('layout', 'align', value as ArtStyle['layout']['align'])} />
                        <Range label="Margem" value={style.layout.margin} min={2} max={15} step={0.5} suffix="%" onChange={(value) => set('layout', 'margin', value)} />
                        <Check label="Moldura" checked={style.layout.frame} onChange={(value) => set('layout', 'frame', value)} />
                        {style.layout.frame && (
                            <>
                                <Color label="Cor da moldura" value={style.layout.frame_color} onChange={(value) => set('layout', 'frame_color', value)} />
                                <Range label="Espessura" value={style.layout.frame_width} min={0.2} max={3} step={0.1} onChange={(value) => set('layout', 'frame_width', value)} />
                                <Range label="Cantos arredondados" value={style.layout.radius} min={0} max={10} step={0.5} onChange={(value) => set('layout', 'radius', value)} />
                            </>
                        )}
                    </Section>

                    <Section title="Escudos">
                        <Check label="Mostrar escudos" checked={style.crest.show} onChange={(value) => set('crest', 'show', value)} />
                        {style.crest.show && (
                            <>
                                <Range label="Tamanho" value={style.crest.size} min={10} max={45} step={1} suffix="%" onChange={(value) => set('crest', 'size', value)} />
                                <Pick label="Fundo do escudo" value={style.crest.shape} options={options.crest_shapes} onChange={(value) => set('crest', 'shape', value as ArtStyle['crest']['shape'])} />
                                {style.crest.shape !== 'none' && !style.crest.use_team_colors && <Color label="Cor do fundo" value={style.crest.bg} onChange={(value) => set('crest', 'bg', value)} />}
                            </>
                        )}
                    </Section>

                    <Section title="Faixa de transmissão">
                        <Check label="Mostrar a faixa de transmissão (selo da plataforma, chamada e canal)" checked={style.elements.broadcast} onChange={(value) => set('elements', 'broadcast', value)} />
                        {style.elements.broadcast && (
                            <>
                                <Pick label="Plataforma" value={style.broadcast.platform} options={options.platforms} onChange={(value) => set('broadcast', 'platform', value as ArtStyle['broadcast']['platform'])} />
                                <Field label="Chamada">
                                    <Input value={style.broadcast.label} maxLength={40} onChange={(event) => set('broadcast', 'label', event.target.value)} />
                                </Field>
                                <Field label="Canal / organização">
                                    <Input value={style.broadcast.channel} maxLength={60} onChange={(event) => set('broadcast', 'channel', event.target.value)} />
                                </Field>
                            </>
                        )}
                    </Section>

                    <Section title="O que aparece">
                        <div className="grid gap-2 sm:grid-cols-2">
                            {options.elements.filter((element) => element.value !== 'broadcast').map((element) => (
                                <Check key={element.value} label={element.label} checked={style.elements[element.value as keyof ArtStyle['elements']]} onChange={(value) => set('elements', element.value as keyof ArtStyle['elements'], value)} />
                            ))}
                        </div>
                    </Section>
                </div>
            </div>
        </PanelLayout>
    );
}
