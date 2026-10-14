import { Badge, Button, Card, Empty, Field, Input, PageHeader, Select } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { useCan } from '@/lib/can';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useEffect, useState } from 'react';
import OverlayPreviewStudio from './OverlayPreviewStudio';

interface Appearance {
    preset: 'arena' | 'studio';
    accent_color: string;
    background_color: string;
    text_color: string;
    position: 'top-left' | 'top-center' | 'top-right';
    scale: number;
    motion: 'dynamic' | 'subtle' | 'none';
}

interface LineupSettings {
    presentation: 'sequence' | 'list' | 'formation';
    list_seconds: number;
    photo_person_ids: string[];
}

interface LineupPerson {
    id: string;
    name: string;
    shirt: number | null;
    team: string;
    has_photo: boolean;
    photo_url: string | null;
}

interface Channel {
    id: string;
    name: string;
    game_id: string | null;
    game_label: string | null;
    layers: { lineup: boolean; standings: boolean };
    appearance: Appearance;
    lineup_settings: LineupSettings;
    lineup_people: LineupPerson[];
    has_control_token: boolean;
    control_url: string;
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

interface AppearancePreset {
    value: Appearance['preset'];
    label: string;
}

const layerNames: Record<'lineup' | 'standings', string> = { lineup: 'Escalação', standings: 'Tabela' };

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

function ChannelCard({ channel, games, presets, canManage, canOperate, issuedControl }: { channel: Channel; games: GameOption[]; presets: AppearancePreset[]; canManage: boolean; canOperate: boolean; issuedControl?: IssuedControl }) {
    const [appearance, setAppearance] = useState(channel.appearance);
    const [lineupSettings, setLineupSettings] = useState(channel.lineup_settings);
    const patch = (data: { game_id?: string | null; layers?: Record<string, boolean>; appearance?: Partial<Appearance>; lineup_settings?: Partial<LineupSettings> }) => router.patch(route('panel.broadcast.update', channel.id), data, { preserveScroll: true });
    // O jogo atual pode já ter saído da lista de candidatos (ex.: encerrado): mantém a opção para o select refletir o estado real.
    const options = channel.game_id && !games.some((game) => game.id === channel.game_id) ? [{ id: channel.game_id, label: channel.game_label ?? 'Jogo atual' }, ...games] : games;

    useEffect(() => {
        setAppearance(channel.appearance);
        setLineupSettings(channel.lineup_settings);
    }, [channel.appearance, channel.lineup_settings]);

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
            <div className="space-y-5">
                <OverlayPreviewStudio overlays={channel.overlays} hasGame={channel.game_id !== null} layers={channel.layers} />

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

                <div className="rounded-lg border border-gray-200 p-4">
                    <div className="mb-3">
                        <div className="font-medium text-gray-900">Visual do overlay · {presets.find((preset) => preset.value === appearance.preset)?.label ?? appearance.preset}</div>
                        <p className="text-xs text-gray-500">As fontes abertas no OBS recebem as mudanças sem recarregar.</p>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <Field label="Estilo" htmlFor={`preset-${channel.id}`}>
                            <Select id={`preset-${channel.id}`} disabled={!canOperate} value={appearance.preset} onChange={(event) => setAppearance({ ...appearance, preset: event.target.value as Appearance['preset'] })}>
                                {presets.map((preset) => <option key={preset.value} value={preset.value}>{preset.label}</option>)}
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
                            </Select>
                        </Field>
                        <Field label={`Lista antes da transição · ${lineupSettings.list_seconds} s`} htmlFor={`lineup-seconds-${channel.id}`}>
                            <Input id={`lineup-seconds-${channel.id}`} type="range" min="3" max="20" step="1" disabled={!canOperate || lineupSettings.presentation !== 'sequence'} value={lineupSettings.list_seconds} onChange={(event) => setLineupSettings({ ...lineupSettings, list_seconds: Number(event.target.value) })} />
                        </Field>
                    </div>

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

                <div>
                    <div className="mb-2 text-sm font-medium text-gray-700">Camadas sob demanda</div>
                    <div className="flex flex-wrap gap-2">
                        {(Object.keys(layerNames) as ('lineup' | 'standings')[]).map((layer) => (
                            <Button
                                key={layer}
                                variant={channel.layers[layer] ? 'primary' : 'secondary'}
                                disabled={!canOperate || !channel.game_id}
                                onClick={() => patch({ layers: { [layer]: !channel.layers[layer] } })}
                            >
                                {channel.layers[layer] ? `Ocultar ${layerNames[layer].toLowerCase()}` : `Mostrar ${layerNames[layer].toLowerCase()}`}
                            </Button>
                        ))}
                    </div>
                    {!channel.game_id && <p className="mt-1 text-xs text-gray-500">Escolha um jogo no ar para liberar as camadas.</p>}
                </div>

                {canManage && (
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
                )}

            </div>
        </Card>
    );
}

export default function Index({ channels, games, appearance_presets, issued_control }: { channels: Channel[]; games: GameOption[]; appearance_presets: AppearancePreset[]; issued_control?: IssuedControl }) {
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
                    <ChannelCard key={channel.id} channel={channel} games={games} presets={appearance_presets} canManage={canManage} canOperate={canOperate} issuedControl={issued_control} />
                ))}

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
                            Deixe as fontes sempre ativas: <Badge>Placar</Badge> <Badge>Lances</Badge> <Badge>Escalação</Badge> <Badge>Tabela</Badge>. Elas só aparecem quando há o que mostrar.
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
