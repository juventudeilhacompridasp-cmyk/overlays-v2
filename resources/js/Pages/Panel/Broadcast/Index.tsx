import { Badge, Button, Card, Empty, Field, Input, PageHeader, Select } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { useCan } from '@/lib/can';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useEffect, useState } from 'react';
import { LowerThirdSettings } from '@/lib/lowerThird';
import { OBS_VIEWS } from '@/lib/obsChampionship';
import ChampionshipStudio, { ChampionshipSeason, StyleModel } from './ChampionshipStudio';
import ExtraScreensStudio from './ExtraScreensStudio';
import LowerThirdStudio from './LowerThirdStudio';
import OverlayPreviewStudio from './OverlayPreviewStudio';

interface Appearance {
    preset: 'arena' | 'classic' | 'minimal';
    accent_color: string;
    background_color: string;
    text_color: string;
    position: 'top-left' | 'top-center' | 'top-right' | 'bottom-left' | 'bottom-center' | 'bottom-right';
    scale: number;
    motion: 'dynamic' | 'subtle' | 'none';
    layout: 'bar' | 'stacked' | 'compact' | 'wide';
    scorers: boolean;
    font: string;
}

interface Scene {
    id: string;
    name: string;
}

interface CustomFont {
    id: string;
    name: string;
    format: string;
}

const catalogFonts: { value: string; label: string }[] = [
    { value: 'system', label: 'Padrão do sistema' },
    { value: 'inter', label: 'Inter (moderna)' },
    { value: 'montserrat', label: 'Montserrat (geométrica)' },
    { value: 'oswald', label: 'Oswald (condensada)' },
    { value: 'bebas', label: 'Bebas Neue (títulos altos)' },
    { value: 'anton', label: 'Anton (títulos pesados)' },
    { value: 'barlow', label: 'Barlow Condensed' },
    { value: 'roboto-condensed', label: 'Roboto Condensed' },
    { value: 'archivo', label: 'Archivo Black (extra pesada)' },
    { value: 'playfair', label: 'Playfair Display (clássica)' },
    { value: 'marker', label: 'Permanent Marker (manuscrita)' },
];

interface LineupSettings {
    presentation: 'sequence' | 'list' | 'formation' | 'cards' | 'individual' | 'show';
    list_seconds: number;
    photo_person_ids: string[];
    team: 'home' | 'away';
    individual_mode: 'auto' | 'manual';
    individual_seconds: number;
    individual_scope: 'starters' | 'all';
    sponsor_banner: boolean;
    step: number;
    anchor_ms: number;
}

interface LineupPerson {
    id: string;
    name: string;
    shirt: number | null;
    team: string;
    has_photo: boolean;
    photo_url: string | null;
}

interface SponsorSettings {
    enabled: boolean;
    seconds: number;
}

interface Sponsor {
    id: string;
    name: string;
    active: boolean;
    logo_url: string | null;
}

interface Channel {
    id: string;
    name: string;
    game_id: string | null;
    game_label: string | null;
    layers: { lineup: boolean; standings: boolean; scoreboard: boolean; lower_third: boolean };
    championship_live: Record<string, boolean>;
    stoppage: number;
    screens_live: Record<string, boolean>;
    screen_settings: { title: string; subtitle: string; until_ms: number | null };
    appearance: Appearance;
    lineup_settings: LineupSettings;
    lineup_people: LineupPerson[];
    lineup_steps: number;
    sponsor_settings: SponsorSettings;
    lower_third_settings: LowerThirdSettings;
    has_control_token: boolean;
    control_url: string;
    championship_base: string;
    overlays: { slug: string; title: string; url: string }[];
}

interface IssuedControl {
    channel_id: string;
    token: string;
}

interface GameOption {
    id: string;
    label: string;
}

type LayerKey = 'lineup' | 'standings' | 'scoreboard' | 'lower_third';

// Placar e lances começam no ar; escalação e tabela começam ocultas. Só estas duas precisam de jogo no ar.
const layerNames: Record<LayerKey, string> = { scoreboard: 'Placar', lower_third: 'Lances', lineup: 'Escalação', standings: 'Tabela' };
const layersNeedingGame: LayerKey[] = ['lineup', 'standings'];

function CopyButton({ text }: { text: string }) {
    const [copied, setCopied] = useState(false);

    return (
        <Button
            small
            variant="secondary"
            onClick={async () => {
                try {
                    await navigator.clipboard.writeText(text);
                    setCopied(true);
                    window.setTimeout(() => setCopied(false), 1500);
                } catch {
                    window.prompt('Copie a URL:', text);
                }
            }}
        >
            {copied ? 'Copiado!' : 'Copiar'}
        </Button>
    );
}

function ScenesBlock({ channel, scenes, limit, canManage, canOperate }: { channel: Channel; scenes: Scene[]; limit: number; canManage: boolean; canOperate: boolean }) {
    const [name, setName] = useState('');
    const [error, setError] = useState<string | null>(null);

    const save = (event: FormEvent) => {
        event.preventDefault();
        router.post(route('panel.broadcast.scenes.store'), { name, channel_id: channel.id }, {
            preserveScroll: true,
            onSuccess: () => {
                setName('');
                setError(null);
            },
            onError: (errors) => setError(errors.name ?? errors.channel_id ?? 'Não foi possível salvar a cena.'),
        });
    };

    return (
        <div className="rounded-lg border border-gray-200 p-4">
            <div className="mb-3">
                <div className="font-medium text-gray-900">Cenas</div>
                <p className="text-xs text-gray-500">Uma cena guarda o visual, as camadas, os patrocinadores e a apresentação da escalação. Um clique troca tudo de uma vez; o jogo no ar e as fotos escolhidas não mudam.</p>
            </div>
            {scenes.length === 0 && <p className="text-sm text-gray-500">Nenhuma cena salva ainda.</p>}
            <div className="flex flex-wrap gap-2">
                {scenes.map((scene) => (
                    <span key={scene.id} className="inline-flex items-center gap-1">
                        <Button small variant="secondary" disabled={!canOperate} onClick={() => router.patch(route('panel.broadcast.scenes.apply', { channel: channel.id, scene: scene.id }), {}, { preserveScroll: true })}>{scene.name}</Button>
                        {canManage && (
                            <button type="button" aria-label={`Excluir a cena ${scene.name}`} className="rounded px-1 text-xs text-gray-400 hover:text-red-600" onClick={() => window.confirm(`Excluir a cena ${scene.name}?`) && router.delete(route('panel.broadcast.scenes.destroy', scene.id), { preserveScroll: true })}>✕</button>
                        )}
                    </span>
                ))}
            </div>
            {canManage && scenes.length < limit && (
                <form onSubmit={save} className="mt-3 flex flex-wrap items-end gap-3" aria-label="Salvar cena">
                    <Field label="Salvar o visual atual deste canal como cena" htmlFor={`scene-name-${channel.id}`} error={error ?? undefined}>
                        <Input id={`scene-name-${channel.id}`} value={name} onChange={(event) => setName(event.target.value)} maxLength={60} placeholder="Ex.: Pré-jogo" required />
                    </Field>
                    <Button type="submit" small disabled={name.trim() === ''}>Salvar cena</Button>
                </form>
            )}
        </div>
    );
}

function ChannelCard({ channel, games, fonts, scenes, sceneLimit, seasons, styleModels, styleModelLimit, canManage, canOperate, issuedControl }: { channel: Channel; games: GameOption[]; fonts: CustomFont[]; scenes: Scene[]; sceneLimit: number; seasons: ChampionshipSeason[]; styleModels: StyleModel[]; styleModelLimit: number; canManage: boolean; canOperate: boolean; issuedControl?: IssuedControl }) {
    const [appearance, setAppearance] = useState(channel.appearance);
    const [lineupSettings, setLineupSettings] = useState(channel.lineup_settings);
    const [sponsorSettings, setSponsorSettings] = useState(channel.sponsor_settings);
    const patch = (data: { game_id?: string | null; layers?: Record<string, boolean>; championship_live?: Record<string, boolean>; stoppage_minutes?: number; screens_live?: Record<string, boolean>; screen_settings?: { title?: string; subtitle?: string; minutes?: number }; appearance?: Partial<Appearance>; lineup_settings?: Partial<LineupSettings>; lineup_command?: 'next' | 'previous' | 'reset'; sponsor_settings?: Partial<SponsorSettings>; lower_third_settings?: Partial<LowerThirdSettings>; lower_third_show?: { kind: string; title?: string; subtitle?: string; person_id?: string } }, options: { onError?: () => void } = {}) => router.patch(route('panel.broadcast.update', channel.id), data, { preserveScroll: true, ...options });

    // Comandos de exibir/ocultar: o botão muda no clique e a mudança segue direto para o OBS; se o servidor recusar, volta ao estado real.
    const [layers, setLayers] = useState(channel.layers);
    useEffect(() => setLayers(channel.layers), [channel.layers]);
    const toggleLayer = (layer: LayerKey) => {
        const next = !layers[layer];
        setLayers({ ...layers, [layer]: next });
        patch({ layers: { [layer]: next } }, { onError: () => setLayers(channel.layers) });
    };
    // O jogo atual pode já ter saído da lista de candidatos (ex.: encerrado): mantém a opção para o select refletir o estado real.
    const options = channel.game_id && !games.some((game) => game.id === channel.game_id) ? [{ id: channel.game_id, label: channel.game_label ?? 'Jogo atual' }, ...games] : games;

    useEffect(() => {
        setAppearance(channel.appearance);
        setLineupSettings(channel.lineup_settings);
        setSponsorSettings(channel.sponsor_settings);
    }, [channel.appearance, channel.lineup_settings, channel.sponsor_settings]);

    const togglePhoto = (personId: string) => {
        const selected = lineupSettings.photo_person_ids.includes(personId);
        setLineupSettings({
            ...lineupSettings,
            photo_person_ids: selected ? lineupSettings.photo_person_ids.filter((id) => id !== personId) : [...lineupSettings.photo_person_ids, personId],
        });
    };

    return (
        <Card
            title={channel.name}
            actions={
                canManage ? (
                    <div className="flex gap-2">
                        <Button
                            small
                            variant="secondary"
                            onClick={() => {
                                if (window.confirm('Renovar o token? As URLs atuais deixam de funcionar e você precisará atualizar as fontes no OBS.')) {
                                    router.post(route('panel.broadcast.regenerate', channel.id), {}, { preserveScroll: true });
                                }
                            }}
                        >
                            Renovar token
                        </Button>
                        <Button
                            small
                            variant="danger"
                            onClick={() => {
                                if (window.confirm(`Excluir o canal "${channel.name}"? As URLs deixam de funcionar.`)) {
                                    router.delete(route('panel.broadcast.destroy', channel.id), { preserveScroll: true });
                                }
                            }}
                        >
                            Excluir
                        </Button>
                    </div>
                ) : undefined
            }
        >
            <div className="-mx-1 flex snap-x gap-4 overflow-x-auto px-1 pb-3" data-testid="broadcast-board">
                    <section className="w-[26rem] shrink-0 snap-start space-y-4 rounded-xl bg-gray-50 p-3" aria-label="Ao vivo">
                        <h3 className="px-1 text-xs font-semibold uppercase tracking-wide text-gray-500">Ao vivo</h3>
                <div>
                    <Field label="Jogo no ar" htmlFor={`game-${channel.id}`} hint="Todas as telas deste canal passam a acompanhar este jogo, sem mexer no OBS.">
                        <Select id={`game-${channel.id}`} disabled={!canOperate} value={channel.game_id ?? ''} onChange={(event) => patch({ game_id: event.target.value || null })}>
                            <option value="">Nenhum (telas vazias)</option>
                            {options.map((game) => (
                                <option key={game.id} value={game.id}>
                                    {game.label}
                                </option>
                            ))}
                        </Select>
                    </Field>
                </div>

                <div>
                    <div className="mb-2 text-sm font-medium text-gray-700">No ar agora</div>
                    <div className="flex flex-wrap gap-2">
                        {(Object.keys(layerNames) as LayerKey[]).map((layer) => (
                            <Button
                                key={layer}
                                variant={layers[layer] ? 'primary' : 'secondary'}
                                disabled={!canOperate || (layersNeedingGame.includes(layer) && !channel.game_id)}
                                aria-pressed={layers[layer]}
                                onClick={() => toggleLayer(layer)}
                            >
                                {layers[layer] ? `Ocultar ${layerNames[layer].toLowerCase()}` : `Mostrar ${layerNames[layer].toLowerCase()}`}
                            </Button>
                        ))}
                    </div>
                    <p className="mt-1 text-xs text-gray-500">Cada clique vale na hora no OBS, sem esperar. {!channel.game_id && 'Escolha um jogo no ar para liberar a escalação e a tabela.'}</p>
                </div>
                <div>
                    <div className="mb-2 text-sm font-medium text-gray-700">Acréscimos no relógio</div>
                    <div className="flex flex-wrap gap-2">
                        {[0, 1, 2, 3, 4, 5, 6, 7, 8].map((minutes) => (
                            <Button key={minutes} small variant={channel.stoppage === minutes ? 'primary' : 'secondary'} disabled={!canOperate || !channel.game_id} aria-pressed={channel.stoppage === minutes} onClick={() => patch({ stoppage_minutes: minutes })}>{minutes === 0 ? 'Sem' : `+${minutes}`}</Button>
                        ))}
                    </div>
                    <p className="mt-1 text-xs text-gray-500">Aparece ao lado do relógio do placar enquanto o jogo corre; trocar o jogo zera.</p>
                </div>

                <div>
                    <div className="mb-2 text-sm font-medium text-gray-700">Telas de campeonato no ar</div>
                    <div className="flex flex-wrap gap-2">
                        {OBS_VIEWS.map((view) => (
                            <Button key={view.key} small variant={channel.championship_live[view.key] ? 'primary' : 'secondary'} disabled={!canOperate} aria-pressed={channel.championship_live[view.key]} onClick={() => patch({ championship_live: { [view.key]: !channel.championship_live[view.key] } })}>
                                {channel.championship_live[view.key] ? 'Ocultar' : 'Mostrar'} {view.label.toLowerCase()}
                            </Button>
                        ))}
                    </div>
                    <p className="mt-1 text-xs text-gray-500">Vale para as URLs de campeonato criadas com “Controle ao vivo” ligado; as outras ficam sempre no ar.</p>
                </div>

                <ScenesBlock channel={channel} scenes={scenes} limit={sceneLimit} canManage={canManage} canOperate={canOperate} />
                    </section>
                    <section className="w-[44rem] shrink-0 snap-start space-y-4 rounded-xl bg-gray-50 p-3" aria-label="Pré-visualização">
                        <h3 className="px-1 text-xs font-semibold uppercase tracking-wide text-gray-500">Pré-visualização</h3>
                <OverlayPreviewStudio overlays={channel.overlays} hasGame={channel.game_id !== null} layers={{ ...channel.layers, screens: channel.screens_live }} />
                    </section>
                    <section className="w-[26rem] shrink-0 snap-start space-y-4 rounded-xl bg-gray-50 p-3" aria-label="Visual">
                        <h3 className="px-1 text-xs font-semibold uppercase tracking-wide text-gray-500">Visual</h3>
                <div className="rounded-lg border border-gray-200 p-4">
                    <div className="mb-3">
                        <div className="font-medium text-gray-900">Visual do overlay</div>
                        <p className="text-xs text-gray-500">As fontes abertas no OBS recebem as mudanças sem recarregar.</p>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <Field label="Estilo do placar" htmlFor={`preset-${channel.id}`}>
                            <Select id={`preset-${channel.id}`} disabled={!canOperate} value={appearance.preset} onChange={(event) => setAppearance({ ...appearance, preset: event.target.value as Appearance['preset'] })}>
                                <option value="arena">Arena (alto contraste)</option>
                                <option value="classic">Clássico (barra de TV)</option>
                                <option value="minimal">Minimal (pílula translúcida)</option>
                            </Select>
                        </Field>
                        <Field label="Formato do placar" htmlFor={`layout-${channel.id}`}>
                            <Select id={`layout-${channel.id}`} disabled={!canOperate} value={appearance.layout} onChange={(event) => setAppearance({ ...appearance, layout: event.target.value as Appearance['layout'] })}>
                                <option value="bar">Barra horizontal</option>
                                <option value="stacked">Equipes empilhadas</option>
                                <option value="compact">Compacto (pílula pequena)</option>
                                <option value="wide">Faixa larga (ponta a ponta)</option>
                            </Select>
                        </Field>
                        <label className="flex items-center gap-2 self-end pb-2 text-sm text-gray-700">
                            <input type="checkbox" className="rounded border-gray-300 text-teal-600 focus:ring-teal-500" disabled={!canOperate} checked={appearance.scorers} onChange={(event) => setAppearance({ ...appearance, scorers: event.target.checked })} />
                            Mostrar autores dos gols sob o placar
                        </label>
                        <Field label="Fonte" htmlFor={`font-${channel.id}`}>
                            <Select id={`font-${channel.id}`} disabled={!canOperate} value={appearance.font} onChange={(event) => setAppearance({ ...appearance, font: event.target.value })}>
                                {catalogFonts.map((font) => (
                                    <option key={font.value} value={font.value}>{font.label}</option>
                                ))}
                                {fonts.length > 0 && (
                                    <optgroup label="Fontes da organização">
                                        {fonts.map((font) => (
                                            <option key={font.id} value={font.id}>{font.name}</option>
                                        ))}
                                    </optgroup>
                                )}
                            </Select>
                        </Field>
                        <Field label="Cor de destaque" htmlFor={`accent-${channel.id}`}>
                            <Input id={`accent-${channel.id}`} type="color" disabled={!canOperate} value={appearance.accent_color} onChange={(event) => setAppearance({ ...appearance, accent_color: event.target.value })} className="h-10" />
                        </Field>
                        <Field label="Fundo" htmlFor={`background-${channel.id}`}>
                            <Input id={`background-${channel.id}`} type="color" disabled={!canOperate} value={appearance.background_color} onChange={(event) => setAppearance({ ...appearance, background_color: event.target.value })} className="h-10" />
                        </Field>
                        <Field label="Texto" htmlFor={`text-${channel.id}`}>
                            <Input id={`text-${channel.id}`} type="color" disabled={!canOperate} value={appearance.text_color} onChange={(event) => setAppearance({ ...appearance, text_color: event.target.value })} className="h-10" />
                        </Field>
                        <Field label="Posição do placar" htmlFor={`position-${channel.id}`}>
                            <Select id={`position-${channel.id}`} disabled={!canOperate} value={appearance.position} onChange={(event) => setAppearance({ ...appearance, position: event.target.value as Appearance['position'] })}>
                                <option value="top-left">Superior esquerdo</option>
                                <option value="top-center">Superior central</option>
                                <option value="top-right">Superior direito</option>
                                <option value="bottom-left">Inferior esquerdo</option>
                                <option value="bottom-center">Inferior central</option>
                                <option value="bottom-right">Inferior direito</option>
                            </Select>
                        </Field>
                        <Field label={`Escala · ${Math.round(appearance.scale * 100)}%`} htmlFor={`scale-${channel.id}`}>
                            <Input id={`scale-${channel.id}`} type="range" min="0.75" max="1.5" step="0.05" disabled={!canOperate} value={appearance.scale} onChange={(event) => setAppearance({ ...appearance, scale: Number(event.target.value) })} />
                        </Field>
                        <Field label="Animações" htmlFor={`motion-${channel.id}`}>
                            <Select id={`motion-${channel.id}`} disabled={!canOperate} value={appearance.motion} onChange={(event) => setAppearance({ ...appearance, motion: event.target.value as Appearance['motion'] })}>
                                <option value="dynamic">Dinâmicas</option>
                                <option value="subtle">Suaves</option>
                                <option value="none">Sem animação</option>
                            </Select>
                        </Field>
                    </div>
                    {canOperate && <Button small className="mt-3" onClick={() => patch({ appearance })}>Aplicar visual</Button>}
                </div>
                    </section>
                    <section className="w-[30rem] shrink-0 snap-start space-y-4 rounded-xl bg-gray-50 p-3" aria-label="Escalação">
                        <h3 className="px-1 text-xs font-semibold uppercase tracking-wide text-gray-500">Escalação</h3>
                <div className="rounded-lg border border-gray-200 p-4">
                    <div className="mb-3">
                        <div className="font-medium text-gray-900">Escalação visual</div>
                        <p className="text-xs text-gray-500">Apresente a lista e transicione para as posições definidas pelo gestor no esquema tático.</p>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Field label="Apresentação" htmlFor={`lineup-presentation-${channel.id}`}>
                            <Select id={`lineup-presentation-${channel.id}`} disabled={!canOperate} value={lineupSettings.presentation} onChange={(event) => setLineupSettings({ ...lineupSettings, presentation: event.target.value as LineupSettings['presentation'] })}>
                                <option value="sequence">Lista e depois esquema tático</option>
                                <option value="list">Somente lista</option>
                                <option value="formation">Somente esquema tático</option>
                                <option value="cards">Cartões da equipe (titulares + comissão)</option>
                                <option value="individual">Jogador por jogador</option>
                                <option value="show">Cartões e depois jogador por jogador</option>
                            </Select>
                        </Field>
                        <Field label={`Lista antes da transição · ${lineupSettings.list_seconds} s`} htmlFor={`lineup-seconds-${channel.id}`}>
                            <Input id={`lineup-seconds-${channel.id}`} type="range" min="3" max="20" step="1" disabled={!canOperate || lineupSettings.presentation !== 'sequence'} value={lineupSettings.list_seconds} onChange={(event) => setLineupSettings({ ...lineupSettings, list_seconds: Number(event.target.value) })} />
                        </Field>
                    </div>

                    {['cards', 'individual', 'show'].includes(lineupSettings.presentation) && (
                        <div className="mt-4 space-y-3 border-t border-gray-100 pt-4">
                            <div className="text-sm font-medium text-gray-800">Apresentação por equipe</div>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <Field label="Equipe no ar" htmlFor={`lineup-team-${channel.id}`}>
                                    <Select id={`lineup-team-${channel.id}`} disabled={!canOperate} value={lineupSettings.team} onChange={(event) => setLineupSettings({ ...lineupSettings, team: event.target.value as LineupSettings['team'] })}>
                                        <option value="home">Mandante</option>
                                        <option value="away">Visitante</option>
                                    </Select>
                                </Field>
                                <Field label="Quem aparece um a um" htmlFor={`lineup-scope-${channel.id}`}>
                                    <Select id={`lineup-scope-${channel.id}`} disabled={!canOperate} value={lineupSettings.individual_scope} onChange={(event) => setLineupSettings({ ...lineupSettings, individual_scope: event.target.value as LineupSettings['individual_scope'] })}>
                                        <option value="starters">Titulares e técnico</option>
                                        <option value="all">Titulares, reservas e técnico</option>
                                    </Select>
                                </Field>
                                {lineupSettings.presentation !== 'cards' && (
                                    <>
                                        <Field label="Passagem dos jogadores" htmlFor={`lineup-mode-${channel.id}`}>
                                            <Select id={`lineup-mode-${channel.id}`} disabled={!canOperate} value={lineupSettings.individual_mode} onChange={(event) => setLineupSettings({ ...lineupSettings, individual_mode: event.target.value as LineupSettings['individual_mode'] })}>
                                                <option value="auto">Automática, a cada X segundos</option>
                                                <option value="manual">Manual (o operador passa um a um)</option>
                                            </Select>
                                        </Field>
                                        <Field label={`Tempo de cada jogador · ${lineupSettings.individual_seconds} s`} htmlFor={`lineup-individual-${channel.id}`}>
                                            <Input id={`lineup-individual-${channel.id}`} type="range" min="3" max="30" step="1" disabled={!canOperate || lineupSettings.individual_mode !== 'auto'} value={lineupSettings.individual_seconds} onChange={(event) => setLineupSettings({ ...lineupSettings, individual_seconds: Number(event.target.value) })} />
                                        </Field>
                                    </>
                                )}
                            </div>
                            {lineupSettings.presentation === 'show' && <p className="text-xs text-gray-500">Os cartões ficam {lineupSettings.list_seconds} s no ar antes do primeiro jogador (ajuste em "Lista antes da transição").</p>}
                            <label className="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" className="rounded border-gray-300 text-teal-600 focus:ring-teal-500" disabled={!canOperate} checked={lineupSettings.sponsor_banner} onChange={(event) => setLineupSettings({ ...lineupSettings, sponsor_banner: event.target.checked })} />
                                Mostrar a faixa de patrocinador no rodapé
                            </label>
                            {lineupSettings.presentation !== 'cards' && canOperate && (
                                <div className="rounded-md bg-gray-50 p-3">
                                    <div className="mb-2 text-sm font-medium text-gray-800">Passar ao vivo{channel.lineup_settings.individual_mode === 'manual' ? ` · etapa ${channel.lineup_settings.step + 1} de ${channel.lineup_steps}` : ''}</div>
                                    <div className="flex flex-wrap gap-2">
                                        <Button small variant="secondary" disabled={!channel.game_id} onClick={() => patch({ lineup_command: 'previous' })}>← Anterior</Button>
                                        <Button small disabled={!channel.game_id} onClick={() => patch({ lineup_command: 'next' })}>Próximo →</Button>
                                        <Button small variant="secondary" disabled={!channel.game_id} onClick={() => patch({ lineup_command: 'reset' })}>↺ Reiniciar</Button>
                                    </div>
                                    <p className="mt-1 text-xs text-gray-500">Vale na hora no OBS, também no modo automático (pula para o jogador anterior ou seguinte e segue a contagem).</p>
                                </div>
                            )}
                        </div>
                    )}

                    <div className="mt-4 border-t border-gray-100 pt-4">
                        <div className="text-sm font-medium text-gray-800">Fotos no ar</div>
                        <p className="mt-1 text-xs text-amber-700">Ative individualmente. A foto selecionada ficará visível para quem tiver a URL secreta do overlay.</p>
                        {!channel.game_id && <p className="mt-2 text-sm text-gray-500">Escolha o jogo no ar para selecionar atletas.</p>}
                        {channel.game_id && channel.lineup_people.length === 0 && <p className="mt-2 text-sm text-gray-500">A escalação deste jogo ainda não foi salva.</p>}
                        {channel.lineup_people.length > 0 && (
                            <div className="mt-3 grid gap-2 sm:grid-cols-2">
                                {channel.lineup_people.map((person) => {
                                    const checked = lineupSettings.photo_person_ids.includes(person.id);

                                    return (
                                        <label key={person.id} className={`flex items-center gap-3 rounded-lg border p-2 ${checked ? 'border-teal-400 bg-teal-50' : 'border-gray-200'} ${person.has_photo ? 'cursor-pointer' : 'opacity-60'}`}>
                                            <input type="checkbox" className="rounded border-gray-300 text-teal-600 focus:ring-teal-500" checked={checked} disabled={!canOperate || !person.has_photo} onChange={() => togglePhoto(person.id)} />
                                            <span className="grid h-9 w-9 shrink-0 place-items-center overflow-hidden rounded-full bg-gray-200 text-xs font-bold text-gray-600">
                                                {person.photo_url ? <img src={person.photo_url} alt="" className="h-full w-full object-cover" /> : (person.shirt ?? '—')}
                                            </span>
                                            <span className="min-w-0">
                                                <span className="block truncate text-sm font-medium text-gray-900">{person.shirt ? `${person.shirt} · ` : ''}{person.name}</span>
                                                <span className="block truncate text-xs text-gray-500">{person.team}{person.has_photo ? '' : ' · sem foto cadastrada'}</span>
                                            </span>
                                        </label>
                                    );
                                })}
                            </div>
                        )}
                    </div>
                    {canOperate && <Button small className="mt-4" onClick={() => patch({ lineup_settings: lineupSettings })}>Aplicar escalação</Button>}
                </div>
                    </section>
                    <section className="w-[30rem] shrink-0 snap-start space-y-4 rounded-xl bg-gray-50 p-3" aria-label="Telas extras">
                        <h3 className="px-1 text-xs font-semibold uppercase tracking-wide text-gray-500">Telas extras (pré-jogo, comparativo, cartela)</h3>
                        <ExtraScreensStudio channelId={channel.id} screens={channel.screens_live} settings={channel.screen_settings} hasGame={channel.game_id !== null} canOperate={canOperate} patch={(data) => patch(data)} />
                    </section>
                    <section className="w-[30rem] shrink-0 snap-start space-y-4 rounded-xl bg-gray-50 p-3" aria-label="Lances">
                        <h3 className="px-1 text-xs font-semibold uppercase tracking-wide text-gray-500">Lances (lower thirds)</h3>
                        <LowerThirdStudio channelId={channel.id} settings={channel.lower_third_settings} people={channel.lineup_people} hasGame={channel.game_id !== null} visible={channel.layers.lower_third} canOperate={canOperate} patch={(data) => patch(data)} />
                    </section>
                    <section className="w-[34rem] shrink-0 snap-start space-y-4 rounded-xl bg-gray-50 p-3" aria-label="Campeonato">
                        <h3 className="px-1 text-xs font-semibold uppercase tracking-wide text-gray-500">Campeonato (tabela, jogos, artilharia…)</h3>
                        <ChampionshipStudio channelId={channel.id} base={channel.championship_base} seasons={seasons} models={styleModels} modelLimit={styleModelLimit} canManage={canManage} />
                    </section>
                    <section className="w-[26rem] shrink-0 snap-start space-y-4 rounded-xl bg-gray-50 p-3" aria-label="Patrocinadores">
                        <h3 className="px-1 text-xs font-semibold uppercase tracking-wide text-gray-500">Patrocinadores</h3>
                <div className="rounded-lg border border-gray-200 p-4">
                    <div className="mb-3">
                        <div className="font-medium text-gray-900">Patrocinadores</div>
                        <p className="text-xs text-gray-500">Gira um logotipo por vez na tela <strong>Patrocinadores</strong> do OBS. A lista fica logo abaixo dos canais; a posição, a escala e o estilo seguem o visual acima.</p>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <label className="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" className="rounded border-gray-300 text-teal-600 focus:ring-teal-500" disabled={!canOperate} checked={sponsorSettings.enabled} onChange={(event) => {
                                setSponsorSettings({ ...sponsorSettings, enabled: event.target.checked });
                                patch({ sponsor_settings: { enabled: event.target.checked } }, { onError: () => setSponsorSettings(channel.sponsor_settings) });
                            }} />
                            Mostrar a rotação de patrocinadores (vale na hora)
                        </label>
                        <Field label={`Tempo de cada logotipo · ${sponsorSettings.seconds}s`} htmlFor={`sponsor-seconds-${channel.id}`}>
                            <Input id={`sponsor-seconds-${channel.id}`} type="range" min="3" max="30" step="1" disabled={!canOperate} value={sponsorSettings.seconds} onChange={(event) => setSponsorSettings({ ...sponsorSettings, seconds: Number(event.target.value) })} />
                        </Field>
                    </div>
                    {canOperate && <Button small className="mt-3" onClick={() => patch({ sponsor_settings: sponsorSettings })}>Aplicar patrocinadores</Button>}
                </div>
                    </section>
                    {canManage && (
                    <section className="w-[26rem] shrink-0 snap-start space-y-4 rounded-xl bg-gray-50 p-3" aria-label="API">
                        <h3 className="px-1 text-xs font-semibold uppercase tracking-wide text-gray-500">API</h3>
                    <div className="rounded-lg border border-gray-200 p-4">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div className="font-medium text-gray-900">Controle por API</div>
                                <p className="text-xs text-gray-500">Use uma chave separada das URLs públicas para trocar jogo, camadas e visual.</p>
                            </div>
                            <Button
                                small
                                variant="secondary"
                                onClick={() => {
                                    if (!channel.has_control_token || window.confirm('Renovar a chave? A chave atual deixará de funcionar imediatamente.')) {
                                        router.post(route('panel.broadcast.control-token', channel.id), {}, { preserveScroll: true });
                                    }
                                }}
                            >
                                {channel.has_control_token ? 'Renovar chave' : 'Gerar chave'}
                            </Button>
                        </div>
                        <code className="mt-2 block break-all rounded bg-gray-100 px-3 py-2 text-xs text-gray-700">PATCH {channel.control_url}</code>
                        {issuedControl?.channel_id === channel.id && (
                            <div className="mt-3 rounded bg-amber-50 p-3 text-sm text-amber-800">
                                <strong>Copie agora; esta chave não será exibida novamente.</strong>
                                <code className="mt-2 block break-all rounded bg-white px-2 py-1 text-xs text-gray-900">{issuedControl.token}</code>
                                <div className="mt-2"><CopyButton text={issuedControl.token} /></div>
                            </div>
                        )}
                    </div>
                
                    </section>
                    )}
            </div>
        </Card>
    );
}

function SponsorsCard({ sponsors, limit, canManage }: { sponsors: Sponsor[]; limit: number; canManage: boolean }) {
    const [name, setName] = useState('');
    const [logo, setLogo] = useState<File | null>(null);
    const [error, setError] = useState<string | null>(null);

    const add = (event: FormEvent) => {
        event.preventDefault();
        if (!logo) return;
        router.post(route('panel.broadcast.sponsors.store'), { name, logo }, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setName('');
                setLogo(null);
                setError(null);
            },
            onError: (errors) => setError(errors.logo ?? errors.name ?? 'Não foi possível cadastrar.'),
        });
    };

    return (
        <Card title="Patrocinadores dos overlays">
            <p className="mb-3 text-xs text-gray-500">Os logotipos valem para todos os canais da organização ({sponsors.length}/{limit}). Aparecem na tela Patrocinadores do OBS quando a rotação está ligada no canal e o patrocinador está ativo.</p>
            {sponsors.length === 0 && <p className="text-sm text-gray-500">Nenhum patrocinador cadastrado.</p>}
            <ul className="divide-y divide-gray-100">
                {sponsors.map((sponsor) => (
                    <li key={sponsor.id} className="flex items-center gap-3 py-2">
                        <span className="grid h-12 w-20 place-items-center rounded bg-[#ffffff] p-1 ring-1 ring-gray-200">{sponsor.logo_url && <img src={sponsor.logo_url} alt="" className="max-h-full max-w-full object-contain" />}</span>
                        <span className="flex-1 text-sm font-medium text-gray-900">{sponsor.name}</span>
                        {sponsor.active ? <Badge>Ativo</Badge> : <Badge>Pausado</Badge>}
                        {canManage && (
                            <>
                                <Button small variant="secondary" onClick={() => router.patch(route('panel.broadcast.sponsors.update', sponsor.id), { active: !sponsor.active }, { preserveScroll: true })}>{sponsor.active ? 'Pausar' : 'Ativar'}</Button>
                                <Button small variant="danger" onClick={() => window.confirm(`Excluir ${sponsor.name}?`) && router.delete(route('panel.broadcast.sponsors.destroy', sponsor.id), { preserveScroll: true })}>Excluir</Button>
                            </>
                        )}
                    </li>
                ))}
            </ul>
            {canManage && sponsors.length < limit && (
                <form onSubmit={add} className="mt-4 flex flex-wrap items-end gap-3" aria-label="Novo patrocinador">
                    <Field label="Nome" htmlFor="sponsor-name">
                        <Input id="sponsor-name" value={name} onChange={(event) => setName(event.target.value)} maxLength={80} required />
                    </Field>
                    <Field label="Logotipo (JPG, PNG ou WebP, até 5 MB)" htmlFor="sponsor-logo" error={error ?? undefined}>
                        <input id="sponsor-logo" type="file" accept="image/jpeg,image/png,image/webp" onChange={(event) => setLogo(event.target.files?.[0] ?? null)} className="text-sm" required />
                    </Field>
                    <Button type="submit" disabled={!logo || name.trim() === ''}>Cadastrar</Button>
                </form>
            )}
        </Card>
    );
}

function FontsCard({ fonts, limit, canManage }: { fonts: CustomFont[]; limit: number; canManage: boolean }) {
    const [name, setName] = useState('');
    const [file, setFile] = useState<File | null>(null);
    const [error, setError] = useState<string | null>(null);

    const add = (event: FormEvent) => {
        event.preventDefault();
        if (!file) return;
        router.post(route('panel.broadcast.fonts.store'), { name, font: file }, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setName('');
                setFile(null);
                setError(null);
            },
            onError: (errors) => setError(errors.font ?? errors.name ?? 'Não foi possível enviar.'),
        });
    };

    return (
        <Card title="Fontes próprias dos overlays">
            <p className="mb-3 text-xs text-gray-500">Envie a fonte da identidade visual (WOFF2, WOFF, TTF ou OTF, até 2 MB; {fonts.length}/{limit}). Ela aparece na lista de fontes de cada canal. Use só fontes que a sua organização tem licença para usar na transmissão.</p>
            {fonts.length === 0 && <p className="text-sm text-gray-500">Nenhuma fonte própria enviada.</p>}
            <ul className="divide-y divide-gray-100">
                {fonts.map((font) => (
                    <li key={font.id} className="flex items-center gap-3 py-2">
                        <span className="flex-1 text-sm font-medium text-gray-900">{font.name}</span>
                        <Badge>{font.format.toUpperCase()}</Badge>
                        {canManage && <Button small variant="danger" onClick={() => window.confirm(`Excluir ${font.name}? Os canais que a usam voltam à fonte padrão.`) && router.delete(route('panel.broadcast.fonts.destroy', font.id), { preserveScroll: true })}>Excluir</Button>}
                    </li>
                ))}
            </ul>
            {canManage && fonts.length < limit && (
                <form onSubmit={add} className="mt-4 flex flex-wrap items-end gap-3" aria-label="Nova fonte">
                    <Field label="Nome" htmlFor="font-name">
                        <Input id="font-name" value={name} onChange={(event) => setName(event.target.value)} maxLength={60} required />
                    </Field>
                    <Field label="Arquivo da fonte" htmlFor="font-file" error={error ?? undefined}>
                        <input id="font-file" type="file" accept=".woff2,.woff,.ttf,.otf" onChange={(event) => setFile(event.target.files?.[0] ?? null)} className="text-sm" required />
                    </Field>
                    <Button type="submit" disabled={!file || name.trim() === ''}>Enviar fonte</Button>
                </form>
            )}
        </Card>
    );
}

export default function Index({ channels, games, issued_control, sponsors, sponsor_limit, fonts, font_limit, scenes, scene_limit, style_models, style_model_limit, championship_seasons }: { channels: Channel[]; games: GameOption[]; issued_control?: IssuedControl; sponsors: Sponsor[]; sponsor_limit: number; fonts: CustomFont[]; font_limit: number; scenes: Scene[]; scene_limit: number; style_models: StyleModel[]; style_model_limit: number; championship_seasons: ChampionshipSeason[] }) {
    const canManage = useCan('manage-competitions');
    const canOperate = useCan('operate-games');
    const { data, setData, post, processing, errors, reset } = useForm({ name: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(route('panel.broadcast.store'), { preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <PanelLayout>
            <Head title="Transmissão" />
            <PageHeader title="Transmissão" subtitle="Overlays para o OBS com fundo transparente. Cada canal tem URLs fixas; durante a transmissão você só escolhe o jogo no ar e liga as camadas." />

            <div className="space-y-6">
                {channels.length === 0 && <Empty title="Nenhum canal de transmissão ainda.">{canManage ? 'Crie um canal abaixo para gerar as URLs do OBS.' : 'Peça a um organizador para criar um canal.'}</Empty>}

                {channels.map((channel) => (
                    <ChannelCard key={channel.id} channel={channel} games={games} fonts={fonts} scenes={scenes} sceneLimit={scene_limit} seasons={championship_seasons} styleModels={style_models} styleModelLimit={style_model_limit} canManage={canManage} canOperate={canOperate} issuedControl={issued_control} />
                ))}

                <SponsorsCard sponsors={sponsors} limit={sponsor_limit} canManage={canManage} />

                <FontsCard fonts={fonts} limit={font_limit} canManage={canManage} />

                {canManage && (
                    <Card title="Novo canal">
                        <form onSubmit={submit} className="flex flex-wrap items-end gap-3" aria-label="Novo canal">
                            <Field label="Nome do canal" htmlFor="channel-name" error={errors.name}>
                                <Input id="channel-name" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="Ex.: Transmissão principal" required />
                            </Field>
                            <Button type="submit" disabled={processing}>
                                Criar canal
                            </Button>
                        </form>
                    </Card>
                )}

                <Card title="Como configurar no OBS">
                    <ol className="list-decimal space-y-2 pl-5 text-sm text-gray-700">
                        <li>
                            No OBS, adicione uma <strong>Fonte de navegador</strong> por tela e cole a URL correspondente.
                        </li>
                        <li>
                            Use <strong>largura 1920</strong> e <strong>altura 1080</strong> (o fundo já é transparente; não precisa de CSS personalizado).
                        </li>
                        <li>
                            Deixe as fontes sempre ativas: <Badge>Placar</Badge> <Badge>Lances</Badge> <Badge>Escalação</Badge> <Badge>Tabela</Badge> <Badge>Patrocinadores</Badge>. Elas só aparecem quando há o que mostrar.
                        </li>
                        <li>Escalação e tabela ficam escondidas até você clicar em "Mostrar" aqui; os lances (gol, cartão, substituição) aparecem sozinhos quando a súmula é lançada.</li>
                        <li>
                            Quem tiver a URL vê o canal: trate-a como senha. Se vazar, use <strong>Renovar token</strong>.
                        </li>
                    </ol>
                </Card>
            </div>
        </PanelLayout>
    );
}
