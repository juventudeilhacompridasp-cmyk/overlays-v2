import { Card, LinkButton, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { useCan } from '@/lib/can';
import { Head } from '@inertiajs/react';

interface Props {
    counts: { competitions: number; teams: number; people: number; games_finished: number };
    games_by_month: { label: string; value: number }[];
    status_breakdown: Record<string, number>;
    upcoming_games: { id: string; scheduled_at: string | null; status: string; home: string; away: string; division: string | null }[];
}

const statusLabels: Record<string, string> = { scheduled: 'Agendados', in_progress: 'Em andamento', finished: 'Encerrados', postponed: 'Adiados' };

export default function Dashboard({ counts, games_by_month, status_breakdown, upcoming_games }: Props) {
    const canRegistry = useCan('manage-registry');
    const canCompetitions = useCan('manage-competitions');
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

            <div className="mt-6 grid gap-6 lg:grid-cols-[1.4fr_1fr]">
                <Card title="Jogos encerrados">
                    <p className="-mt-2 mb-2 text-xs text-gray-500">Últimos seis meses</p>
                    <div className="flex h-48 items-end gap-3 px-2 pt-4" role="img" aria-label="Jogos encerrados por mês">
                        {games_by_month.map((month) => {
                            const max = Math.max(1, ...games_by_month.map((item) => item.value));
                            const height = `${Math.max(5, (month.value / max) * 100)}%`;
                            return <div key={month.label} className="group flex h-full flex-1 flex-col items-center justify-end gap-2">
                                <span className="text-xs font-semibold text-gray-600 opacity-0 transition-opacity group-hover:opacity-100">{month.value}</span>
                                <div className="w-full max-w-12 rounded-t-md bg-gradient-to-t from-indigo-600 to-cyan-400 transition-[height] duration-700 ease-out" style={{ height }} aria-label={`${month.label}: ${month.value}`} />
                                <span className="text-[10px] text-gray-500">{month.label}</span>
                            </div>;
                        })}
                    </div>
                </Card>

                <Card title="Situação dos jogos">
                    <p className="-mt-2 mb-4 text-xs text-gray-500">Visão atual da competição</p>
                    <div className="space-y-4 pt-2">
                        {Object.entries(status_breakdown).map(([status, value]) => {
                            const total = Math.max(1, Object.values(status_breakdown).reduce((sum, item) => sum + item, 0));
                            return <div key={status}>
                                <div className="mb-1 flex justify-between text-sm"><span className="text-gray-600">{statusLabels[status] ?? status}</span><strong className="text-gray-900">{value}</strong></div>
                                <div className="h-2 overflow-hidden rounded-full bg-gray-100"><div className="h-full rounded-full bg-indigo-500 transition-[width] duration-700" style={{ width: `${(value / total) * 100}%` }} /></div>
                            </div>;
                        })}
                    </div>
                </Card>
            </div>

            <Card title="Próximos jogos" className="mt-6">
                <p className="-mt-2 mb-4 text-xs text-gray-500">Agenda operacional</p>
                {upcoming_games.length === 0 ? <p className="text-sm text-gray-500">Nenhum jogo agendado.</p> : <div className="divide-y divide-gray-100">
                    {upcoming_games.map((game) => <div key={game.id} className="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                        <div><div className="font-semibold text-gray-900">{game.home} <span className="text-gray-400">×</span> {game.away}</div><div className="text-xs text-gray-500">{game.division ?? 'Divisão'} · {game.status}</div></div>
                        <time className="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">{game.scheduled_at ? new Date(game.scheduled_at).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : 'Sem horário'}</time>
                    </div>)}
                </div>}
            </Card>

            <Card title="Por onde começar" className="mt-6">
                <ol className="list-decimal space-y-2 pl-5 text-sm text-gray-700">
                    <li>Cadastre as <strong>equipes</strong> e os <strong>estádios</strong>.</li>
                    <li>Crie a <strong>competição</strong>, depois a <strong>edição</strong> (com o regulamento) e as <strong>divisões</strong> (categorias).</li>
                    <li>Em cada divisão, cadastre as <strong>fases</strong> e <strong>inscreva as equipes</strong>.</li>
                </ol>
                <div className="mt-4 flex flex-wrap gap-2">
                    {canRegistry && <LinkButton href={route('panel.teams.create')} variant="secondary">Nova equipe</LinkButton>}
                    {canCompetitions && <LinkButton href={route('panel.competitions.create')}>Nova competição</LinkButton>}
                </div>
            </Card>
        </PanelLayout>
    );
}
