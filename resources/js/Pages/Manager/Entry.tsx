import { Badge, Button, Card, Empty, Input, LinkButton, Logo, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { useFeature } from '@/lib/can';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

interface Member {
    id: string;
    name: string;
    nickname: string | null;
    shirt_number: number | null;
    role: string;
    role_label: string;
    age: number | null;
    photo_url: string | null;
    yellow: number;
    red: number;
    suspended_games: number;
    series_yellows: number;
}

interface Supplement {
    id: string;
    status: string;
    status_label: string;
    people_count: number;
    review_note: string | null;
    created_at: string | null;
    url: string | null;
}

interface Props {
    entry: { id: string; team: string; logo_url: string | null; competition: string; season: string; division: string };
    roster: Member[];
    window: { closed_reason: string | null };
    max_athletes: number;
    supplements: Supplement[];
    changes: { id: string; text: string; by: string | null; at: string | null }[];
}

const supplementTone = (status: string) => (status === 'approved' ? 'green' : status === 'rejected' ? 'red' : status === 'submitted' ? 'amber' : 'gray');

export default function Entry({ entry, roster, window: rosterWindow, max_athletes, supplements, changes }: Props) {
    const athletes = roster.filter((member) => member.role === 'athlete');
    const staff = roster.filter((member) => member.role !== 'athlete');
    const locked = rosterWindow.closed_reason !== null;
    const canRequest = useFeature('online_registration');
    const hasCards = useFeature('athlete_cards');
    const open = supplements.find((item) => item.url !== null);

    return (
        <PanelLayout>
            <Head title={`Elenco · ${entry.team}`} />
            <PageHeader
                title={entry.team}
                subtitle={`${entry.competition} · ${entry.season} · ${entry.division}`}
                breadcrumbs={[{ label: 'Início', href: route('manager.dashboard') }, { label: 'Elenco' }]}
                actions={
                    <>
                        {hasCards && (
                            <LinkButton variant="secondary" href={route('manager.entries.cards', entry.id)}>
                                Carteirinhas
                            </LinkButton>
                        )}
                        {canRequest && !locked && (
                            <Button onClick={() => router.post(route('manager.entries.supplement', entry.id))}>{open ? 'Continuar pedido de reforço' : 'Solicitar reforços'}</Button>
                        )}
                    </>
                }
            />

            {locked && (
                <div role="status" className="mb-4 rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200">
                    {rosterWindow.closed_reason}
                </div>
            )}

            <Card title={`Atletas (${athletes.length} de ${max_athletes})`} className="mb-6">
                {athletes.length === 0 ? <Empty title="Nenhum atleta no elenco" /> : <MemberTable members={athletes} showShirt editable={!locked} />}
            </Card>

            <Card title={`Comissão técnica (${staff.length})`} className="mb-6">
                {staff.length === 0 ? <Empty title="Ninguém na comissão" /> : <MemberTable members={staff} editable={!locked} />}
            </Card>

            {supplements.length > 0 && (
                <Card title="Pedidos de reforço" className="mb-6">
                    <ul className="divide-y divide-gray-100">
                        {supplements.map((item) => (
                            <li key={item.id} className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                                <div>
                                    <span className="text-gray-900">Pedido de {item.created_at}</span>
                                    <span className="ml-2 text-gray-500">{item.people_count} pessoa(s)</span>
                                    {item.review_note && <div className="text-xs text-red-700">Recado da organização: {item.review_note}</div>}
                                </div>
                                <div className="flex items-center gap-2">
                                    <Badge tone={supplementTone(item.status)}>{item.status_label}</Badge>
                                    {item.url && (
                                        <a href={item.url} className="text-emerald-700 hover:underline">
                                            Abrir
                                        </a>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                </Card>
            )}

            {changes.length > 0 && (
                <Card title="Últimas alterações no elenco">
                    <ul className="space-y-1 text-sm text-gray-700">
                        {changes.map((change) => (
                            <li key={change.id}>
                                {change.text}
                                <span className="ml-2 text-xs text-gray-400">
                                    {change.at}
                                    {change.by ? ` · ${change.by}` : ''}
                                </span>
                            </li>
                        ))}
                    </ul>
                </Card>
            )}
        </PanelLayout>
    );
}

function MemberTable({ members, showShirt = false, editable }: { members: Member[]; showShirt?: boolean; editable: boolean }) {
    const [editing, setEditing] = useState<string | null>(null);

    return (
        <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
                <thead className="text-xs uppercase text-gray-500">
                    <tr>
                        {showShirt && <th className="py-2 pr-3">Nº</th>}
                        <th className="py-2 pr-3">Nome</th>
                        <th className="py-2 pr-3">Função</th>
                        <th className="py-2 pr-3">Idade</th>
                        <th className="py-2 pr-3">Cartões</th>
                        <th className="py-2 pr-3">Situação</th>
                        {editable && <th className="py-2" />}
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                    {members.map((member) =>
                        editing === member.id ? (
                            <EditRow key={member.id} member={member} showShirt={showShirt} onDone={() => setEditing(null)} />
                        ) : (
                            <tr key={member.id}>
                                {showShirt && <td className="py-2 pr-3 tabular-nums text-gray-700">{member.shirt_number ?? '—'}</td>}
                                <td className="py-2 pr-3">
                                    <div className="flex items-center gap-2">
                                        <Logo src={member.photo_url} name={member.name} size={28} round />
                                        <div>
                                            <div className="font-medium text-gray-900">{member.nickname || member.name}</div>
                                            {member.nickname && <div className="text-xs text-gray-500">{member.name}</div>}
                                        </div>
                                    </div>
                                </td>
                                <td className="py-2 pr-3 text-gray-600">{member.role_label}</td>
                                <td className="py-2 pr-3 text-gray-600">{member.age ?? '—'}</td>
                                <td className="py-2 pr-3 text-gray-600">
                                    {member.yellow > 0 && <span className="mr-2">🟨 {member.yellow}</span>}
                                    {member.red > 0 && <span>🟥 {member.red}</span>}
                                    {member.yellow === 0 && member.red === 0 && '—'}
                                </td>
                                <td className="py-2 pr-3">
                                    {member.suspended_games > 0 ? (
                                        <Badge tone="red">Suspenso ({member.suspended_games} jogo{member.suspended_games > 1 ? 's' : ''})</Badge>
                                    ) : member.series_yellows > 0 ? (
                                        <Badge tone="amber">{member.series_yellows} amarelo{member.series_yellows > 1 ? 's' : ''} na série</Badge>
                                    ) : (
                                        <Badge tone="green">Regular</Badge>
                                    )}
                                </td>
                                {editable && (
                                    <td className="py-2 text-right">
                                        <div className="flex justify-end gap-2">
                                            <Button small variant="secondary" onClick={() => setEditing(member.id)}>
                                                Editar
                                            </Button>
                                            <Button
                                                small
                                                variant="danger"
                                                onClick={() =>
                                                    window.confirm(`Desligar ${member.nickname || member.name} do elenco? Ele(a) sai também das escalações dos jogos que ainda não começaram.`) &&
                                                    router.delete(route('manager.roster.destroy', member.id), { preserveScroll: true })
                                                }
                                            >
                                                Desligar
                                            </Button>
                                        </div>
                                    </td>
                                )}
                            </tr>
                        ),
                    )}
                </tbody>
            </table>
        </div>
    );
}

function EditRow({ member, showShirt, onDone }: { member: Member; showShirt: boolean; onDone: () => void }) {
    const [shirt, setShirt] = useState(member.shirt_number === null ? '' : String(member.shirt_number));
    const [nickname, setNickname] = useState(member.nickname ?? '');
    const [saving, setSaving] = useState(false);

    const save = () => {
        setSaving(true);
        router.patch(
            route('manager.roster.update', member.id),
            { ...(showShirt ? { shirt_number: shirt === '' ? null : Number(shirt) } : {}), nickname: nickname === '' ? null : nickname },
            { preserveScroll: true, onSuccess: onDone, onFinish: () => setSaving(false) },
        );
    };

    return (
        <tr>
            {showShirt && (
                <td className="py-2 pr-3">
                    <Input aria-label="Camisa" type="number" min={0} max={99} className="!w-20" value={shirt} onChange={(event) => setShirt(event.target.value)} />
                </td>
            )}
            <td className="py-2 pr-3" colSpan={4}>
                <Input aria-label="Apelido" placeholder={`Apelido de ${member.name}`} maxLength={60} value={nickname} onChange={(event) => setNickname(event.target.value)} />
            </td>
            <td className="py-2 text-right" colSpan={2}>
                <div className="flex justify-end gap-2">
                    <Button small disabled={saving} onClick={save}>
                        Salvar
                    </Button>
                    <Button small variant="ghost" onClick={onDone}>
                        Cancelar
                    </Button>
                </div>
            </td>
        </tr>
    );
}
