import CustomCanvas from '@/Components/Overlay/CustomCanvas';
import { Button, Card, Field, Input, Select } from '@/Components/Panel/ui';
import { ANIMATIONS, BuilderAnimation, BuilderElement, BuilderItem, CANVAS_SIZES, DataContext, ElementType, IMAGE_TOKENS, TEMPLATES, TEXT_TOKENS, exportOverlay, moveElement, newElement } from '@/lib/customBuilder';
import { OVERLAY_FONT_KEYS, overlayFontFamily } from '@/lib/overlayVisual';
import { router } from '@inertiajs/react';
import type { FormDataConvertible } from '@inertiajs/core';
import { ChangeEvent, PointerEvent as ReactPointerEvent, useEffect, useMemo, useRef, useState } from 'react';

const SAMPLE: DataContext = {
    home: { name: 'Leões FC', short: 'LEO', score: 2, logo: null }, away: { name: 'Tigres EC', short: 'TIG', score: 1, logo: null },
    clock: '35:12', period: '1º tempo', competition: 'Copa Juventude', division: 'Principal', stage: 'Fase de grupos', venue: 'Ginásio Municipal',
    sponsor: { name: 'Padaria Central', logo: null }, time: '19:30',
};

export interface CustomItem extends BuilderItem {}
export interface ChannelCustom {
    id: string;
    name: string;
    url: string;
    live: boolean;
}

/** Por canal: liga e desliga cada overlay livre no OBS e mostra a URL da fonte. */
export function CustomLiveList({ overlays, canOperate, patch }: { overlays: ChannelCustom[]; canOperate: boolean; patch: (data: { custom_live: Record<string, boolean> }) => void }) {
    if (overlays.length === 0) {
        return <p className="text-sm text-gray-500">Nenhum overlay livre ainda. Crie um em “Overlays livres (Builder)”, mais abaixo na página.</p>;
    }

    return (
        <ul className="divide-y divide-gray-100" data-testid="custom-live-list">
            {overlays.map((overlay) => (
                <li key={overlay.id} className="space-y-1 py-2">
                    <div className="flex items-center gap-2">
                        <span className="flex-1 truncate text-sm font-medium text-gray-900">{overlay.name}</span>
                        <Button small variant={overlay.live ? 'primary' : 'secondary'} disabled={!canOperate} aria-pressed={overlay.live} onClick={() => patch({ custom_live: { [overlay.id]: !overlay.live } })}>{overlay.live ? 'Ocultar' : 'Mostrar'}</Button>
                    </div>
                    <input readOnly value={overlay.url} aria-label={`URL de ${overlay.name}`} className="w-full rounded border border-gray-200 bg-gray-50 px-2 py-1 text-xs text-gray-600" onFocus={(event) => event.currentTarget.select()} />
                </li>
            ))}
        </ul>
    );
}

type Draft = { id: string | null; name: string; width: number; height: number; elements: BuilderElement[] };

const blank = (): Draft => ({ id: null, name: '', width: 1920, height: 1080, elements: [] });

/** Editor do overlay livre: lista de elementos, prévia com arrasto, propriedades, modelos, importar e exportar. */
export function CustomEditorCard({ items, limit, sponsors, canManage }: { items: CustomItem[]; limit: number; sponsors: { id: string; name: string }[]; canManage: boolean }) {
    const [draft, setDraft] = useState<Draft>(blank);
    const [selected, setSelected] = useState<string | null>(null);
    const [replay, setReplay] = useState(0);
    const [error, setError] = useState<string | null>(null);
    const stage = useRef<HTMLDivElement>(null);
    const [scale, setScale] = useState(0.4);

    const element = draft.elements.find((candidate) => candidate.id === selected) ?? null;
    const fit = () => setScale(Math.min(1, (stage.current?.clientWidth ?? 640) / draft.width));
    useEffect(() => {
        fit();
        window.addEventListener('resize', fit);

        return () => window.removeEventListener('resize', fit);
    }, [draft.width]); // eslint-disable-line react-hooks/exhaustive-deps

    const update = (id: string, patch: Partial<BuilderElement>) => setDraft((current) => ({ ...current, elements: current.elements.map((candidate) => (candidate.id === id ? { ...candidate, ...patch } : candidate)) }));
    const add = (type: ElementType) => {
        if (draft.elements.length >= 60) return;
        const created = newElement(type, draft.elements.map((candidate) => candidate.id));
        setDraft((current) => ({ ...current, elements: [...current.elements, created] }));
        setSelected(created.id);
    };
    const remove = (id: string) => {
        setDraft((current) => ({ ...current, elements: current.elements.filter((candidate) => candidate.id !== id) }));
        setSelected(null);
    };
    const duplicate = (source: BuilderElement) => {
        if (draft.elements.length >= 60) return;
        const copy = { ...source, id: newElement(source.type, draft.elements.map((candidate) => candidate.id)).id, name: `${source.name} (cópia)`, x: source.x + 2, y: source.y + 2 };
        setDraft((current) => ({ ...current, elements: [...current.elements, copy] }));
        setSelected(copy.id);
    };
    const shift = (id: string, by: number) => setDraft((current) => {
        const index = current.elements.findIndex((candidate) => candidate.id === id);
        const target = index + by;
        if (index < 0 || target < 0 || target >= current.elements.length) return current;
        const next = [...current.elements];
        [next[index], next[target]] = [next[target], next[index]];

        return { ...current, elements: next };
    });

    // Arrastar na prévia: o deslocamento em pixels vira % do canvas.
    const drag = useRef<{ id: string; x: number; y: number } | null>(null);
    const onPointerDown = (id: string, event: ReactPointerEvent) => {
        setSelected(id);
        const target = draft.elements.find((candidate) => candidate.id === id);
        if (!target || target.locked || !canManage) return;
        drag.current = { id, x: event.clientX, y: event.clientY };
        const move = (moveEvent: PointerEvent) => {
            if (!drag.current) return;
            const dx = ((moveEvent.clientX - drag.current.x) / (draft.width * scale)) * 100;
            const dy = ((moveEvent.clientY - drag.current.y) / (draft.height * scale)) * 100;
            drag.current = { id, x: moveEvent.clientX, y: moveEvent.clientY };
            setDraft((current) => ({ ...current, elements: current.elements.map((candidate) => (candidate.id === id ? moveElement(candidate, dx, dy) : candidate)) }));
        };
        const up = () => {
            drag.current = null;
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', up);
        };
        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', up);
    };

    const open = (item: CustomItem) => {
        setDraft({ id: item.id, name: item.name, width: item.width, height: item.height, elements: item.elements });
        setSelected(null);
        setError(null);
        setReplay((value) => value + 1);
    };
    const save = () => {
        setError(null);
        const body = { name: draft.name, width: draft.width, height: draft.height, elements: draft.elements } as unknown as Record<string, FormDataConvertible>;
        const options = { preserveScroll: true, onError: (errors: Record<string, string>) => setError(errors.name ?? Object.values(errors)[0] ?? 'Não foi possível salvar.') };
        if (draft.id) router.patch(route('panel.broadcast.custom.update', draft.id), body, options);
        else router.post(route('panel.broadcast.custom.store'), body, { ...options, onSuccess: () => setDraft(blank()) });
    };
    const download = () => {
        const blob = new Blob([JSON.stringify(exportOverlay(draft), null, 2)], { type: 'application/json' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `${draft.name || 'overlay'}.json`;
        link.click();
        URL.revokeObjectURL(link.href);
    };
    const importFile = async (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        event.target.value = '';
        if (!file || file.size > 1024 * 1024) return setError('Escolha um arquivo .json de até 1 MB.');
        try {
            const data = JSON.parse(await file.text());
            router.post(route('panel.broadcast.custom.import'), data, { preserveScroll: true, onError: (errors) => setError(Object.values(errors)[0] ?? 'Arquivo inválido.') });
        } catch {
            setError('O arquivo não é um JSON válido.');
        }
    };

    const family = useMemo(() => overlayFontFamily('system'), []);

    return (
        <Card title="Overlays livres (Builder)">
            <p className="mb-3 text-xs text-gray-500">Monte telas próprias com textos, imagens e formas, animações de entrada e saída e dados ao vivo do jogo ({items.length}/{limit}). Depois ligue cada uma, por canal, na coluna “Overlays livres”.</p>
            {error && <p className="mb-2 text-sm text-red-600" role="alert">{error}</p>}
            <div className="mb-3 flex flex-wrap items-center gap-2">
                {items.map((item) => <Button key={item.id} small variant={draft.id === item.id ? 'primary' : 'secondary'} onClick={() => open(item)}>{item.name}</Button>)}
                <Button small variant="secondary" onClick={() => { setDraft(blank()); setSelected(null); }} disabled={!canManage}>+ Novo</Button>
                {canManage && (
                    <label className="cursor-pointer rounded-md border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-50">
                        Importar JSON
                        <input type="file" accept="application/json,.json" className="sr-only" aria-label="Importar overlay" onChange={importFile} />
                    </label>
                )}
            </div>

            {draft.id === null && draft.elements.length === 0 && canManage && (
                <div className="mb-3" data-testid="custom-templates">
                    <div className="mb-1 text-sm font-medium text-gray-700">Comece por um modelo</div>
                    <div className="grid gap-2 sm:grid-cols-4">
                        {TEMPLATES.map((template) => (
                            <button key={template.key} type="button" className="rounded-lg border border-gray-200 p-2 text-left text-sm hover:bg-gray-50" onClick={() => { setDraft({ id: null, name: template.key === 'blank' ? draft.name : template.name, width: template.width, height: template.height, elements: template.build() }); setReplay((value) => value + 1); }}>
                                <div className="font-semibold">{template.name}</div>
                                <div className="text-xs text-gray-500">{template.caption} · {template.width} × {template.height}</div>
                            </button>
                        ))}
                    </div>
                </div>
            )}

            <div className="grid gap-4 lg:grid-cols-[14rem_1fr_20rem]">
                <div className="space-y-2">
                    <Field label="Nome" htmlFor="custom-name"><Input id="custom-name" maxLength={60} disabled={!canManage} value={draft.name} onChange={(event) => setDraft({ ...draft, name: event.target.value })} /></Field>
                    <Field label="Tamanho" htmlFor="custom-size">
                        <Select id="custom-size" disabled={!canManage} value={`${draft.width}x${draft.height}`} onChange={(event) => { const [w, h] = event.target.value.split('x').map(Number); setDraft({ ...draft, width: w, height: h }); }}>
                            {CANVAS_SIZES.map((size) => <option key={size.label} value={`${size.width}x${size.height}`}>{size.label}</option>)}
                            {!CANVAS_SIZES.some((size) => size.width === draft.width && size.height === draft.height) && <option value={`${draft.width}x${draft.height}`}>{draft.width} × {draft.height}</option>}
                        </Select>
                    </Field>
                    {canManage && (
                        <div className="flex flex-wrap gap-1">
                            <Button small variant="secondary" onClick={() => add('text')}>+ Texto</Button>
                            <Button small variant="secondary" onClick={() => add('image')}>+ Imagem</Button>
                            <Button small variant="secondary" onClick={() => add('shape')}>+ Forma</Button>
                        </div>
                    )}
                    <ul className="max-h-64 divide-y divide-gray-100 overflow-auto rounded border border-gray-200" data-testid="custom-elements">
                        {[...draft.elements].reverse().map((candidate) => (
                            <li key={candidate.id} className={`flex items-center gap-1 px-2 py-1 text-sm ${selected === candidate.id ? 'bg-teal-50' : ''}`}>
                                <button type="button" className="flex-1 truncate text-left" onClick={() => setSelected(candidate.id)}>{candidate.visible ? '' : '🚫 '}{candidate.locked ? '🔒 ' : ''}{candidate.name}</button>
                                {canManage && <><button type="button" aria-label="Subir" title="Trazer para frente" className="text-gray-500" onClick={() => shift(candidate.id, 1)}>↑</button><button type="button" aria-label="Descer" title="Enviar para trás" className="text-gray-500" onClick={() => shift(candidate.id, -1)}>↓</button></>}
                            </li>
                        ))}
                        {draft.elements.length === 0 && <li className="px-2 py-2 text-xs text-gray-500">Sem elementos.</li>}
                    </ul>
                </div>

                <div>
                    <div ref={stage} className="overflow-hidden rounded border border-gray-200" style={{ background: 'repeating-conic-gradient(#e5e7eb 0% 25%, #f9fafb 0% 50%) 50% / 20px 20px', height: draft.height * scale }} data-testid="custom-stage">
                        <div style={{ width: draft.width, height: draft.height, transform: `scale(${scale})`, transformOrigin: 'top left' }}>
                            <CustomCanvas item={draft} ctx={SAMPLE} live replayKey={replay} fontFamily={family} selectedId={selected} onPointerDown={onPointerDown} />
                        </div>
                    </div>
                    <div className="mt-2 flex flex-wrap items-center gap-2">
                        <Button small variant="secondary" onClick={() => setReplay((value) => value + 1)}>▶ Reproduzir entrada</Button>
                        {canManage && <Button small disabled={draft.name.trim() === ''} onClick={save}>{draft.id ? 'Salvar alterações' : 'Criar overlay'}</Button>}
                        {draft.id && canManage && (
                            <>
                                <Button small variant="secondary" onClick={() => router.post(route('panel.broadcast.custom.duplicate', draft.id!), {}, { preserveScroll: true })}>Duplicar</Button>
                                <Button small variant="secondary" onClick={download}>Exportar JSON</Button>
                                <Button small variant="danger" onClick={() => window.confirm(`Excluir ${draft.name}?`) && router.delete(route('panel.broadcast.custom.destroy', draft.id!), { preserveScroll: true, onSuccess: () => setDraft(blank()) })}>Excluir</Button>
                            </>
                        )}
                    </div>
                    <p className="mt-1 text-xs text-gray-500">Dados de exemplo na prévia. Arraste os elementos para mover; no OBS use uma fonte de navegador do tamanho do overlay.</p>
                </div>

                <div data-testid="custom-props">
                    {element ? <Properties element={element} sponsors={sponsors} canManage={canManage} update={(patch) => update(element.id, patch)} onDuplicate={() => duplicate(element)} onRemove={() => remove(element.id)} /> : <p className="text-sm text-gray-500">Escolha um elemento na lista ou na prévia para editar.</p>}
                </div>
            </div>
        </Card>
    );
}

function Num({ label, value, min, max, step = 1, disabled, onChange }: { label: string; value: number; min: number; max: number; step?: number; disabled: boolean; onChange: (value: number) => void }) {
    return (
        <label className="text-xs text-gray-600">
            {label}
            <input type="number" min={min} max={max} step={step} disabled={disabled} value={value} onChange={(event) => onChange(Number(event.target.value))} className="mt-0.5 w-full rounded border border-gray-300 px-2 py-1 text-sm" />
        </label>
    );
}

function Properties({ element, sponsors, canManage, update, onDuplicate, onRemove }: { element: BuilderElement; sponsors: { id: string; name: string }[]; canManage: boolean; update: (patch: Partial<BuilderElement>) => void; onDuplicate: () => void; onRemove: () => void }) {
    const off = !canManage;
    const color = (label: string, key: 'color' | 'fill' | 'fill2' | 'borderColor') => (
        <label className="text-xs text-gray-600">{label}<input type="color" disabled={off} value={element[key] === '' ? '#000000' : element[key]} onChange={(event) => update({ [key]: event.target.value })} className="mt-0.5 h-8 w-full rounded border border-gray-300" /></label>
    );

    return (
        <div className="space-y-3 text-sm">
            <Field label="Nome do elemento" htmlFor="el-name"><Input id="el-name" maxLength={60} disabled={off} value={element.name} onChange={(event) => update({ name: event.target.value })} /></Field>
            <div className="grid grid-cols-2 gap-2">
                <Num label="X (%)" value={element.x} min={-100} max={200} step={0.5} disabled={off} onChange={(x) => update({ x })} />
                <Num label="Y (%)" value={element.y} min={-100} max={200} step={0.5} disabled={off} onChange={(y) => update({ y })} />
                <Num label="Largura (%)" value={element.w} min={0.5} max={300} step={0.5} disabled={off} onChange={(w) => update({ w })} />
                <Num label="Altura (%)" value={element.h} min={0.5} max={300} step={0.5} disabled={off} onChange={(h) => update({ h })} />
                <Num label="Rotação (°)" value={element.rotation} min={-360} max={360} disabled={off} onChange={(rotation) => update({ rotation })} />
                <Num label="Opacidade (%)" value={element.opacity} min={0} max={100} disabled={off} onChange={(opacity) => update({ opacity })} />
            </div>
            <div className="flex gap-4 text-xs text-gray-700">
                <label className="flex items-center gap-1"><input type="checkbox" disabled={off} checked={element.visible} onChange={(event) => update({ visible: event.target.checked })} />Visível</label>
                <label className="flex items-center gap-1"><input type="checkbox" disabled={off} checked={element.locked} onChange={(event) => update({ locked: event.target.checked })} />Travado</label>
            </div>

            {element.type === 'text' && (
                <div className="space-y-2 rounded border border-gray-200 p-2">
                    <label className="block text-xs text-gray-600">Texto (use {'{dados}'})
                        <textarea disabled={off} maxLength={400} rows={3} value={element.text} onChange={(event) => update({ text: event.target.value })} className="mt-0.5 w-full rounded border border-gray-300 px-2 py-1 text-sm" />
                    </label>
                    <select aria-label="Inserir dado do jogo" disabled={off} value="" onChange={(event) => event.target.value && update({ text: `${element.text}${event.target.value}` })} className="w-full rounded border border-gray-300 px-2 py-1 text-xs">
                        <option value="">Inserir dado do jogo…</option>
                        {TEXT_TOKENS.map((item) => <option key={item.token} value={item.token}>{item.label} {item.token}</option>)}
                    </select>
                    <div className="grid grid-cols-2 gap-2">
                        <label className="text-xs text-gray-600">Fonte
                            <select disabled={off} value={element.font} onChange={(event) => update({ font: event.target.value })} className="mt-0.5 w-full rounded border border-gray-300 px-2 py-1 text-sm">
                                <option value="global">Do canal</option>
                                {OVERLAY_FONT_KEYS.map((key) => <option key={key} value={key}>{key}</option>)}
                            </select>
                        </label>
                        <Num label="Tamanho (px)" value={element.size} min={6} max={800} disabled={off} onChange={(size) => update({ size })} />
                        <label className="text-xs text-gray-600">Peso
                            <select disabled={off} value={element.weight} onChange={(event) => update({ weight: Number(event.target.value) })} className="mt-0.5 w-full rounded border border-gray-300 px-2 py-1 text-sm">{[400, 500, 600, 700, 800, 900].map((weight) => <option key={weight} value={weight}>{weight}</option>)}</select>
                        </label>
                        {color('Cor', 'color')}
                        <label className="text-xs text-gray-600">Alinhar
                            <select disabled={off} value={element.align} onChange={(event) => update({ align: event.target.value as BuilderElement['align'] })} className="mt-0.5 w-full rounded border border-gray-300 px-2 py-1 text-sm"><option value="left">Esquerda</option><option value="center">Centro</option><option value="right">Direita</option></select>
                        </label>
                        <label className="text-xs text-gray-600">Vertical
                            <select disabled={off} value={element.valign} onChange={(event) => update({ valign: event.target.value as BuilderElement['valign'] })} className="mt-0.5 w-full rounded border border-gray-300 px-2 py-1 text-sm"><option value="top">Topo</option><option value="middle">Meio</option><option value="bottom">Base</option></select>
                        </label>
                        <Num label="Espaçamento" value={element.spacing} min={-10} max={40} disabled={off} onChange={(spacing) => update({ spacing })} />
                        <Num label="Entrelinha (%)" value={element.lineHeight} min={70} max={220} disabled={off} onChange={(lineHeight) => update({ lineHeight })} />
                    </div>
                    <div className="flex flex-wrap gap-4 text-xs text-gray-700">
                        <label className="flex items-center gap-1"><input type="checkbox" disabled={off} checked={element.transform === 'uppercase'} onChange={(event) => update({ transform: event.target.checked ? 'uppercase' : 'none' })} />Maiúsculas</label>
                        <label className="flex items-center gap-1"><input type="checkbox" disabled={off} checked={element.italic} onChange={(event) => update({ italic: event.target.checked })} />Itálico</label>
                        <label className="flex items-center gap-1"><input type="checkbox" disabled={off} checked={element.marquee} onChange={(event) => update({ marquee: event.target.checked })} />Texto rolante</label>
                    </div>
                    {element.marquee && <Num label="Volta completa (s)" value={element.marqueeSpeed} min={2} max={90} disabled={off} onChange={(marqueeSpeed) => update({ marqueeSpeed })} />}
                </div>
            )}

            {element.type === 'image' && (
                <div className="space-y-2 rounded border border-gray-200 p-2">
                    <label className="block text-xs text-gray-600">Origem
                        <select disabled={off} value={IMAGE_TOKENS.some((item) => item.token === element.src) || element.src.startsWith('sponsor:') ? element.src : element.src === '' ? '' : 'url'} onChange={(event) => update({ src: event.target.value === 'url' ? 'https://' : event.target.value })} className="mt-0.5 w-full rounded border border-gray-300 px-2 py-1 text-sm">
                            <option value="">Escolha…</option>
                            {IMAGE_TOKENS.map((item) => <option key={item.token} value={item.token}>{item.label}</option>)}
                            {sponsors.map((sponsor) => <option key={sponsor.id} value={`sponsor:${sponsor.id}`}>Logo: {sponsor.name}</option>)}
                            <option value="url">Endereço https…</option>
                        </select>
                    </label>
                    {element.src !== '' && !IMAGE_TOKENS.some((item) => item.token === element.src) && !element.src.startsWith('sponsor:') && (
                        <Input aria-label="Endereço da imagem" maxLength={500} disabled={off} value={element.src} onChange={(event) => update({ src: event.target.value })} placeholder="https://…" />
                    )}
                    <label className="block text-xs text-gray-600">Ajuste
                        <select disabled={off} value={element.fit} onChange={(event) => update({ fit: event.target.value as BuilderElement['fit'] })} className="mt-0.5 w-full rounded border border-gray-300 px-2 py-1 text-sm"><option value="cover">Preencher</option><option value="contain">Conter</option></select>
                    </label>
                </div>
            )}

            <div className="space-y-2 rounded border border-gray-200 p-2">
                <div className="grid grid-cols-2 gap-2">
                    {color('Preenchimento', 'fill')}
                    {color('2ª cor (degradê)', 'fill2')}
                    <Num label="Ângulo (°)" value={element.fillAngle} min={0} max={360} disabled={off} onChange={(fillAngle) => update({ fillAngle })} />
                    <Num label="Opac. fundo (%)" value={element.fillOpacity} min={0} max={100} disabled={off} onChange={(fillOpacity) => update({ fillOpacity })} />
                    <Num label="Cantos (px)" value={element.radius} min={0} max={800} disabled={off} onChange={(radius) => update({ radius })} />
                    <Num label="Margem (px)" value={element.padding} min={0} max={300} disabled={off} onChange={(padding) => update({ padding })} />
                    {color('Borda', 'borderColor')}
                    <Num label="Borda (px)" value={element.borderWidth} min={0} max={80} disabled={off} onChange={(borderWidth) => update({ borderWidth })} />
                </div>
                <div className="flex items-center gap-4 text-xs text-gray-700">
                    <label className="flex items-center gap-1">Sombra
                        <select disabled={off} value={element.shadow} onChange={(event) => update({ shadow: event.target.value as BuilderElement['shadow'] })} className="rounded border border-gray-300 px-1 py-0.5"><option value="none">Nenhuma</option><option value="soft">Suave</option><option value="strong">Forte</option></select>
                    </label>
                    <label className="flex items-center gap-1"><input type="checkbox" disabled={off} checked={element.shape === 'circle'} onChange={(event) => update({ shape: event.target.checked ? 'circle' : 'rect' })} />Redondo</label>
                </div>
            </div>

            <div className="space-y-2 rounded border border-gray-200 p-2">
                <div className="grid grid-cols-2 gap-2">
                    <label className="text-xs text-gray-600">Entrada
                        <select disabled={off} value={element.animIn} onChange={(event) => update({ animIn: event.target.value as BuilderAnimation })} className="mt-0.5 w-full rounded border border-gray-300 px-2 py-1 text-sm">{ANIMATIONS.map((item) => <option key={item.key} value={item.key}>{item.label}</option>)}</select>
                    </label>
                    <label className="text-xs text-gray-600">Saída
                        <select disabled={off} value={element.animOut} onChange={(event) => update({ animOut: event.target.value as BuilderElement['animOut'] })} className="mt-0.5 w-full rounded border border-gray-300 px-2 py-1 text-sm"><option value="same">Igual à entrada (invertida)</option>{ANIMATIONS.map((item) => <option key={item.key} value={item.key}>{item.label}</option>)}</select>
                    </label>
                    <Num label="Atraso (ms)" value={element.delay} min={0} max={10000} step={50} disabled={off} onChange={(delay) => update({ delay })} />
                    <Num label="Duração (ms)" value={element.duration} min={100} max={5000} step={50} disabled={off} onChange={(duration) => update({ duration })} />
                </div>
            </div>

            {canManage && <div className="flex gap-2"><Button small variant="secondary" onClick={onDuplicate}>Duplicar elemento</Button><Button small variant="danger" onClick={onRemove}>Remover</Button></div>}
        </div>
    );
}
