import DataTable from '@/Components/DataTable';
import { Badge, Card, DeleteButton, Empty, LinkButton, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Head, Link } from '@inertiajs/react';

interface Props {
    competition: { id: string; name: string; description: string | null; sport_label: string };
    seasons: { id: string; name: string; year: number; status: string; status_label: string; divisions_count: number }[];
}

const tone = (status: string) => (status === 'active' ? 'green' : status === 'registration' ? 'blue' : status === 'finished' ? 'gray' : 'amber');

export default function Show({ competition, seasons }: Props) {
    return (
        <PanelLayout>
            <Head title={competition.name} />
            <PageHeader
                title={competition.name}
                subtitle={[competition.sport_label, competition.description].filter(Boolean).join(' · ')}
                breadcrumbs={[{ label: 'Competições', href: route('panel.competitions.index') }, { label: competition.name }]}
                actions={
                    <>
                        <LinkButton href={route('panel.competitions.edit', competition.id)} variant="secondary">
                            Editar
                        </LinkButton>
                        <DeleteButton
                            href={route('panel.competitions.destroy', competition.id)}
                            message={`Excluir "${competition.name}" com todas as edições, divisões, fases e jogos? Esta ação não pode ser desfeita.`}
                            small={false}
                        />
                    </>
                }
            />

            <Card
                title="Edições"
                actions={<LinkButton href={route('panel.competitions.seasons.create', competition.id)} small>Nova edição</LinkButton>}
            >
                <DataTable
                    flush
                    caption={`Edições de ${competition.name}`}
                    searchPlaceholder="Buscar edição"
                    rows={seasons}
                    rowKey={(season) => season.id}
                    initialSort={{ key: 'year', dir: 'desc' }}
                    empty={
                        <Empty title="Nenhuma edição cadastrada.">
                            A edição (por exemplo “2026”) guarda o regulamento e as divisões da competição naquele ano.
                        </Empty>
                    }
                    columns={[
                        {
                            key: 'year',
                            header: 'Edição',
                            primary: true,
                            value: (season) => season.year,
                            cell: (season) => (
                                <Link href={route('panel.seasons.show', season.id)} className="font-medium text-gray-900 hover:text-emerald-700">
                                    {season.name}
                                </Link>
                            ),
                        },
                        { key: 'divisions', header: 'Divisões', value: (season) => season.divisions_count, cell: (season) => <span className="text-gray-600">{season.divisions_count}</span> },
                        { key: 'status', header: 'Situação', filter: true, value: (season) => season.status_label, cell: (season) => <Badge tone={tone(season.status)}>{season.status_label}</Badge> },
                        {
                            key: 'actions',
                            header: 'Ações',
                            align: 'right',
                            mobileLabel: false,
                            cell: (season) => (
                                <LinkButton href={route('panel.seasons.show', season.id)} variant="secondary" small>
                                    Abrir
                                </LinkButton>
                            ),
                        },
                    ]}
                />
            </Card>
        </PanelLayout>
    );
}
