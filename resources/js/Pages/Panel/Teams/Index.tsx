import DataTable, { serverTable } from '@/Components/DataTable';
import { Badge, DeleteButton, LinkButton, Logo, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { useCan } from '@/lib/can';
import { Paginated, TableFilters } from '@/types/panel';
import { Head, Link } from '@inertiajs/react';

interface Team {
    id: string;
    name: string;
    short_name: string | null;
    city: string | null;
    primary_color: string | null;
    logo_url: string | null;
    entries_count: number;
}

export default function Index({ teams, filters, city_options }: { teams: Paginated<Team>; filters: TableFilters; city_options: string[] }) {
    const canManage = useCan('manage-registry');
    const empty = filters.q === '' && Object.values(filters.values).every((value) => value === '');

    return (
        <PanelLayout>
            <Head title="Equipes" />
            <PageHeader
                title="Equipes"
                subtitle="Clubes e times da organização. Eles se inscrevem nas divisões de cada edição."
                actions={canManage ? <LinkButton href={route('panel.teams.create')}>Nova equipe</LinkButton> : undefined}
            />

            <DataTable
                caption="Equipes"
                searchPlaceholder="Buscar por nome, sigla ou cidade"
                rows={teams.data}
                rowKey={(team) => team.id}
                server={serverTable(route('panel.teams.index'), teams, filters, { sort: 'name' }, ['teams', 'filters'])}
                empty={empty ? 'Nenhuma equipe cadastrada ainda. Comece cadastrando a primeira.' : undefined}
                columns={[
                    {
                        key: 'name',
                        header: 'Equipe',
                        primary: true,
                        value: (team) => team.name,
                        cell: (team) => (
                            <div className="flex items-center gap-3">
                                <Logo src={team.logo_url} name={team.name} color={team.primary_color} />
                                <Link href={route('panel.teams.show', team.id)} className="font-medium text-gray-900 hover:text-emerald-700">
                                    {team.name}
                                </Link>
                                {team.short_name && <Badge>{team.short_name}</Badge>}
                            </div>
                        ),
                    },
                    {
                        key: 'city',
                        header: 'Cidade',
                        value: (team) => team.city,
                        filter: city_options.map((city) => ({ value: city, label: city })),
                        cell: (team) => <span className="text-gray-600">{team.city ?? '—'}</span>,
                    },
                    {
                        key: 'entries',
                        header: 'Inscrições',
                        value: (team) => team.entries_count,
                        filter: [
                            { value: 'with', label: 'Com inscrição' },
                            { value: 'without', label: 'Sem inscrição' },
                        ],
                        cell: (team) => <span className="text-gray-600">{team.entries_count}</span>,
                    },
                    {
                        key: 'actions',
                        header: 'Ações',
                        align: 'right',
                        mobileLabel: false,
                        cell: (team) => (
                            <div className="flex flex-wrap justify-end gap-2">
                                <LinkButton href={route('panel.teams.show', team.id)} variant="secondary" small>
                                    Abrir
                                </LinkButton>
                                {canManage && (
                                    <>
                                        <LinkButton href={route('panel.teams.edit', team.id)} variant="secondary" small>
                                            Editar
                                        </LinkButton>
                                        <DeleteButton href={route('panel.teams.destroy', team.id)} message={`Excluir a equipe "${team.name}"?`} />
                                    </>
                                )}
                            </div>
                        ),
                    },
                ]}
            />
        </PanelLayout>
    );
}
