import { Card, LinkButton, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Head } from '@inertiajs/react';

interface Props {
    counts: { competitions: number; teams: number; people: number; games_finished: number };
}

export default function Dashboard({ counts }: Props) {
    const stats = [
        { label: 'Competições', value: counts.competitions },
        { label: 'Equipes', value: counts.teams },
        { label: 'Atletas e comissão', value: counts.people },
        { label: 'Jogos encerrados', value: counts.games_finished },
    ];

    return (
        <PanelLayout>
            <Head title="Painel" />
            <PageHeader title="Painel" subtitle="Visão geral da sua organização." />

            <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                {stats.map((stat) => (
                    <div key={stat.label} className="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200">
                        <div className="text-3xl font-bold text-gray-900">{stat.value}</div>
                        <div className="mt-1 text-sm text-gray-500">{stat.label}</div>
                    </div>
                ))}
            </div>

            <Card title="Por onde começar" className="mt-6">
                <ol className="list-decimal space-y-2 pl-5 text-sm text-gray-700">
                    <li>Cadastre as <strong>equipes</strong> e os <strong>estádios</strong>.</li>
                    <li>Crie a <strong>competição</strong>, depois a <strong>edição</strong> (com o regulamento) e as <strong>divisões</strong> (categorias).</li>
                    <li>Em cada divisão, cadastre as <strong>fases</strong> e <strong>inscreva as equipes</strong>.</li>
                </ol>
                <div className="mt-4 flex flex-wrap gap-2">
                    <LinkButton href={route('panel.teams.create')} variant="secondary">Nova equipe</LinkButton>
                    <LinkButton href={route('panel.competitions.create')}>Nova competição</LinkButton>
                </div>
            </Card>
        </PanelLayout>
    );
}
