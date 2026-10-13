import { Badge, Card, Empty, Logo, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Head } from '@inertiajs/react';

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

interface Props {
    entry: { id: string; team: string; logo_url: string | null; competition: string; season: string; division: string };
    roster: Member[];
}

export default function Entry({ entry, roster }: Props) {
    const athletes = roster.filter((member) => member.role === 'athlete');
    const staff = roster.filter((member) => member.role !== 'athlete');

    return (
        <PanelLayout>
            <Head title={`Elenco · ${entry.team}`} />
            <PageHeader
                title={entry.team}
                subtitle={`${entry.competition} · ${entry.season} · ${entry.division}`}
                breadcrumbs={[{ label: 'Início', href: route('manager.dashboard') }, { label: 'Elenco' }]}
            />

            <Card title={`Atletas (${athletes.length})`} className="mb-6">
                {athletes.length === 0 ? <Empty title="Nenhum atleta no elenco" /> : <MemberTable members={athletes} showShirt />}
            </Card>

            <Card title={`Comissão técnica (${staff.length})`}>
                {staff.length === 0 ? <Empty title="Ninguém na comissão" /> : <MemberTable members={staff} />}
            </Card>
        </PanelLayout>
    );
}

function MemberTable({ members, showShirt = false }: { members: Member[]; showShirt?: boolean }) {
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
                        <th className="py-2">Situação</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                    {members.map((member) => (
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
                            <td className="py-2">
                                {member.suspended_games > 0 ? (
                                    <Badge tone="red">Suspenso ({member.suspended_games} jogo{member.suspended_games > 1 ? 's' : ''})</Badge>
                                ) : member.series_yellows > 0 ? (
                                    <Badge tone="amber">{member.series_yellows} amarelo{member.series_yellows > 1 ? 's' : ''} na série</Badge>
                                ) : (
                                    <Badge tone="green">Regular</Badge>
                                )}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
