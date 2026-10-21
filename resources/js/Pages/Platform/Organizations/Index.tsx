import DataTable from '@/Components/DataTable';
import { Badge, Empty, LinkButton, PageHeader } from '@/Components/Panel/ui';
import PlatformLayout from '@/Layouts/PlatformLayout';
import { Head } from '@inertiajs/react';

interface Usage {
    users: number;
    competitions: number;
    seasons: number;
    teams: number;
    people: number;
    games: number;
    finished_games: number;
    registrations: number;
}

interface Row {
    id: string;
    name: string;
    slug: string;
    plan_label: string;
    features_count: number;
    usage: Usage;
    last_activity: string | null;
}

export default function Index({ organizations, features_total }: { organizations: Row[]; features_total: number }) {
    return (
        <PlatformLayout>
            <Head title="Plataforma · organizações" />
            <PageHeader title="Organizações" subtitle="Pacote, recursos liberados e uso de cada organização. A liberação é manual." actions={<LinkButton href={route('platform.organizations.create')}>Nova organização</LinkButton>} />
            <DataTable
                caption="Organizações da plataforma"
                searchPlaceholder="Buscar organização"
                rows={organizations}
                rowKey={(organization) => organization.id}
                persistKey="platform-organizations"
                empty={<Empty title="Nenhuma organização." />}
                columns={[
                    {
                        key: 'name',
                        header: 'Organização',
                        primary: true,
                        value: (organization) => `${organization.name} ${organization.slug}`,
                        cell: (organization) => (
                            <>
                                <span className="font-medium text-gray-900">{organization.name}</span>
                                <span className="ml-2 text-xs text-gray-500">{organization.slug}</span>
                            </>
                        ),
                    },
                    { key: 'plan', header: 'Pacote', value: (organization) => organization.plan_label, filter: true, cell: (organization) => <Badge tone="blue">{organization.plan_label}</Badge> },
                    {
                        key: 'features',
                        header: 'Recursos',
                        value: (organization) => organization.features_count,
                        cell: (organization) => (
                            <span className="text-gray-600">
                                {organization.features_count}/{features_total}
                            </span>
                        ),
                    },
                    { key: 'users', header: 'Usuários', align: 'right', hideBelow: 'xl', value: (organization) => organization.usage.users, cell: (organization) => organization.usage.users },
                    { key: 'competitions', header: 'Competições', align: 'right', hideBelow: 'xl', value: (organization) => organization.usage.competitions, cell: (organization) => organization.usage.competitions },
                    { key: 'teams', header: 'Equipes', align: 'right', hideBelow: 'xl', value: (organization) => organization.usage.teams, cell: (organization) => organization.usage.teams },
                    { key: 'people', header: 'Pessoas', align: 'right', hideBelow: 'xl', value: (organization) => organization.usage.people, cell: (organization) => organization.usage.people },
                    {
                        key: 'games',
                        header: 'Jogos encerrados',
                        align: 'right',
                        value: (organization) => organization.usage.finished_games,
                        cell: (organization) => `${organization.usage.finished_games}/${organization.usage.games}`,
                    },
                    { key: 'registrations', header: 'Inscrições', align: 'right', hideBelow: 'lg', value: (organization) => organization.usage.registrations, cell: (organization) => organization.usage.registrations },
                    {
                        key: 'activity',
                        header: 'Última atividade',
                        value: (organization) => organization.last_activity,
                        hideBelow: 'lg',
                        cell: (organization) => <span className="text-gray-600">{organization.last_activity ?? 'sem atividade'}</span>,
                    },
                    {
                        key: 'actions',
                        header: 'Ações',
                        align: 'right',
                        mobileLabel: false,
                        cell: (organization) => (
                            <LinkButton href={route('platform.organizations.edit', organization.id)} variant="secondary" small>
                                Gerenciar
                            </LinkButton>
                        ),
                    },
                ]}
            />
        </PlatformLayout>
    );
}
