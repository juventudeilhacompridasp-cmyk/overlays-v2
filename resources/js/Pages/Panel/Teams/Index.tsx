import { Badge, Card, DeleteButton, Empty, LinkButton, Logo, PageHeader, Pagination, SearchBox } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Paginated } from '@/types/panel';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface Team {
    id: string;
    name: string;
    short_name: string | null;
    city: string | null;
    primary_color: string | null;
    logo_url: string | null;
    entries_count: number;
}

export default function Index({ teams, filters }: { teams: Paginated<Team>; filters: { q: string } }) {
    const [search, setSearch] = useState(filters.q);

    return (
        <PanelLayout>
            <Head title="Equipes" />
            <PageHeader
                title="Equipes"
                subtitle="Clubes e times da organização. Eles se inscrevem nas divisões de cada edição."
                actions={<LinkButton href={route('panel.teams.create')}>Nova equipe</LinkButton>}
            />

            <div className="mb-4">
                <SearchBox
                    value={search}
                    onChange={setSearch}
                    onSubmit={() => router.get(route('panel.teams.index'), { q: search }, { preserveState: true })}
                    placeholder="Buscar equipe pelo nome"
                />
            </div>

            {teams.data.length === 0 ? (
                <Empty title={filters.q ? 'Nenhuma equipe encontrada.' : 'Nenhuma equipe cadastrada ainda.'}>
                    {!filters.q && 'Comece cadastrando a primeira equipe.'}
                </Empty>
            ) : (
                <Card>
                    <div className="-m-5 overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-100 text-sm">
                            <thead className="bg-gray-50 text-left text-xs uppercase text-gray-500">
                                <tr>
                                    <th className="px-5 py-3">Equipe</th>
                                    <th className="px-5 py-3">Cidade</th>
                                    <th className="px-5 py-3">Inscrições</th>
                                    <th className="px-5 py-3 text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {teams.data.map((team) => (
                                    <tr key={team.id}>
                                        <td className="px-5 py-3">
                                            <div className="flex items-center gap-3">
                                                <Logo src={team.logo_url} name={team.name} color={team.primary_color} />
                                                <Link href={route('panel.teams.edit', team.id)} className="font-medium text-gray-900 hover:text-emerald-700">
                                                    {team.name}
                                                </Link>
                                                {team.short_name && <Badge>{team.short_name}</Badge>}
                                            </div>
                                        </td>
                                        <td className="px-5 py-3 text-gray-600">{team.city ?? '—'}</td>
                                        <td className="px-5 py-3 text-gray-600">{team.entries_count}</td>
                                        <td className="px-5 py-3">
                                            <div className="flex justify-end gap-2">
                                                <LinkButton href={route('panel.teams.edit', team.id)} variant="secondary" small>
                                                    Editar
                                                </LinkButton>
                                                <DeleteButton
                                                    href={route('panel.teams.destroy', team.id)}
                                                    message={`Excluir a equipe "${team.name}"?`}
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            )}
            <Pagination links={teams.links} />
        </PanelLayout>
    );
}
