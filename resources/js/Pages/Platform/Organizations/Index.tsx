import { Badge, Card, Empty, LinkButton, PageHeader } from '@/Components/Panel/ui';
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
            {organizations.length === 0 ? (
                <Empty title="Nenhuma organização." />
            ) : (
                <Card>
                    <ul className="-my-3 divide-y divide-gray-100">
                        {organizations.map((organization) => (
                            <li key={organization.id} className="flex flex-wrap items-center justify-between gap-3 py-3">
                                <div>
                                    <span className="font-medium text-gray-900">{organization.name}</span>{' '}
                                    <Badge tone="blue">{organization.plan_label}</Badge>{' '}
                                    <span className="text-xs text-gray-500">
                                        {organization.features_count}/{features_total} recursos
                                    </span>
                                    <div className="mt-0.5 text-sm text-gray-500">
                                        {organization.usage.users} usuários · {organization.usage.competitions} competições · {organization.usage.teams} equipes · {organization.usage.people} pessoas ·{' '}
                                        {organization.usage.finished_games}/{organization.usage.games} jogos encerrados · {organization.usage.registrations} inscrições
                                        {' · '}
                                        {organization.last_activity ? `atividade em ${organization.last_activity}` : 'sem atividade'}
                                    </div>
                                </div>
                                <LinkButton href={route('platform.organizations.edit', organization.id)} variant="secondary" small>
                                    Gerenciar
                                </LinkButton>
                            </li>
                        ))}
                    </ul>
                </Card>
            )}
        </PlatformLayout>
    );
}
