import { Badge, Card, LinkButton, Logo, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Head } from '@inertiajs/react';
import { GameRow } from './GameRow';

export default function Show({ game }: { game: GameRow }) {
    return (
        <PanelLayout>
            <Head title={`${game.my_team} x ${game.opponent ?? 'A definir'}`} />
            <PageHeader
                title={`${game.my_team} x ${game.opponent ?? 'A definir'}`}
                subtitle={`${game.competition} · ${game.stage}`}
                breadcrumbs={[{ label: 'Jogos', href: route('manager.games.index') }, { label: 'Jogo' }]}
                actions={<LinkButton variant="secondary" href={route('manager.games.index')}>Voltar</LinkButton>}
            />

            <Card>
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="flex items-center gap-4">
                        <Logo src={game.my_logo} name={game.my_team} size={48} />
                        <div className="text-2xl font-bold tabular-nums text-gray-900">
                            {game.decided ? `${game.my_score} x ${game.opponent_score}` : 'x'}
                        </div>
                        <Logo src={game.opponent_logo} name={game.opponent ?? '?'} size={48} />
                    </div>
                    <div className="text-sm text-gray-600">
                        <Badge tone={game.decided ? 'gray' : 'blue'}>{game.status_label}</Badge>
                        <div className="mt-2">{game.scheduled_at ?? 'Horário a definir'}</div>
                        {game.venue && <div>{game.venue}</div>}
                        <div>{game.home ? 'Você é o mandante' : 'Você é o visitante'}</div>
                    </div>
                </div>
            </Card>
        </PanelLayout>
    );
}
