import { Badge, Card, DeleteButton, Empty, LinkButton, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Head, Link } from '@inertiajs/react';

interface Props {
    competition: { id: string; name: string; description: string | null };
    seasons: { id: string; name: string; year: number; status: string; status_label: string; divisions_count: number }[];
}

const tone = (status: string) => (status === 'active' ? 'green' : status === 'registration' ? 'blue' : status === 'finished' ? 'gray' : 'amber');

export default function Show({ competition, seasons }: Props) {
    return (
        <PanelLayout>
            <Head title={competition.name} />
            <PageHeader
                title={competition.name}
                subtitle={competition.description ?? undefined}
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
                {seasons.length === 0 ? (
                    <Empty title="Nenhuma edição cadastrada.">
                        A edição (por exemplo “2026”) guarda o regulamento e as divisões da competição naquele ano.
                    </Empty>
                ) : (
                    <ul className="-my-3 divide-y divide-gray-100">
                        {seasons.map((season) => (
                            <li key={season.id} className="flex items-center justify-between gap-3 py-3">
                                <div>
                                    <Link href={route('panel.seasons.show', season.id)} className="font-medium text-gray-900 hover:text-emerald-700">
                                        {season.name}
                                    </Link>
                                    <div className="mt-0.5 text-sm text-gray-500">
                                        {season.divisions_count} {season.divisions_count === 1 ? 'divisão' : 'divisões'}
                                    </div>
                                </div>
                                <div className="flex items-center gap-3">
                                    <Badge tone={tone(season.status)}>{season.status_label}</Badge>
                                    <LinkButton href={route('panel.seasons.show', season.id)} variant="secondary" small>
                                        Abrir
                                    </LinkButton>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>
        </PanelLayout>
    );
}
