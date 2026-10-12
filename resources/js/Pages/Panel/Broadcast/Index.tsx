import { Badge, Button, Card, Empty, Field, Input, PageHeader, Select } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { useCan } from '@/lib/can';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface Channel {
    id: string;
    name: string;
    game_id: string | null;
    game_label: string | null;
    layers: { lineup: boolean; standings: boolean };
    overlays: { slug: string; title: string; url: string }[];
}

interface GameOption {
    id: string;
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

function ChannelCard({ channel, games, canManage, canOperate }: { channel: Channel; games: GameOption[]; canManage: boolean; canOperate: boolean }) {
    const patch = (data: { game_id?: string | null; layers?: Record<string, boolean> }) => router.patch(route('panel.broadcast.update', channel.id), data, { preserveScroll: true });
    // O jogo atual pode já ter saído da lista de candidatos (ex.: encerrado): mantém a opção para o select refletir o estado real.
    const options = channel.game_id && !games.some((game) => game.id === channel.game_id) ? [{ id: channel.game_id, label: channel.game_label ?? 'Jogo atual' }, ...games] : games;

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

                <div>
                    <div className="mb-2 text-sm font-medium text-gray-700">URLs para o OBS (Fonte de navegador)</div>
                    <ul className="divide-y divide-gray-100 rounded-md border border-gray-200">
                        {channel.overlays.map((overlay) => (
                            <li key={overlay.slug} className="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-sm">
                                <div className="min-w-0">
                                    <div className="font-medium text-gray-900">{overlay.title}</div>
                                    <code className="block truncate text-xs text-gray-500">{overlay.url}</code>
                                </div>
                                <div className="flex gap-2">
                                    <CopyButton text={overlay.url} />
                                    <a href={overlay.url} target="_blank" rel="noreferrer" className="inline-flex items-center rounded-md bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-200">
                                        Abrir
                                    </a>
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>
            </div>
        </Card>
    );
}

export default function Index({ channels, games }: { channels: Channel[]; games: GameOption[] }) {
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
                    <ChannelCard key={channel.id} channel={channel} games={games} canManage={canManage} canOperate={canOperate} />
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
