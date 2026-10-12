import { Link } from '@inertiajs/react';

export interface PortalTeam {
    id: string;
    name: string;
    short_name: string | null;
    logo_url: string | null;
    url: string;
}

export interface PortalGame {
    id: string;
    url: string;
    number: number | null;
    round: number | null;
    group: string | null;
    leg: number | null;
    home: PortalTeam | null;
    away: PortalTeam | null;
    home_score: number;
    away_score: number;
    home_penalties: number | null;
    away_penalties: number | null;
    status: string;
    status_label: string;
    decided: boolean;
    live: boolean;
    scheduled_at: string | null;
    venue: string | null;
    competition?: string;
}

/** "2026-05-10T16:30" → "10/05 às 16:30" (sem fuso: é o horário marcado no local). */
export function formatWhen(value: string | null): string {
    if (!value) return 'Data a definir';
    const [date, time] = value.split('T');
    const [, month, day] = date.split('-');

    return `${day}/${month}${time ? ` às ${time}` : ''}`;
}

function Crest({ team }: { team: PortalTeam | null }) {
    if (!team?.logo_url) return <span className="inline-block h-6 w-6 shrink-0 rounded bg-gray-200" aria-hidden="true" />;

    return <img src={team.logo_url} alt="" width={24} height={24} loading="lazy" className="h-6 w-6 shrink-0 object-contain" />;
}

export function GameRow({ game }: { game: PortalGame }) {
    const score = game.decided || game.live ? `${game.home_score} x ${game.away_score}` : 'x';

    return (
        <li className="py-3">
            <div className="flex items-center gap-2 text-sm sm:gap-4">
                <div className="flex flex-1 items-center justify-end gap-2 text-right font-medium">
                    {game.home ? <Link href={game.home.url} className="hover:underline">{game.home.name}</Link> : 'A definir'}
                    <Crest team={game.home} />
                </div>
                <Link href={game.url} className="min-w-16 rounded bg-neutral-900 px-2 py-1 text-center font-bold tabular-nums text-white hover:bg-neutral-700" aria-label="Ver o jogo">
                    {score}
                </Link>
                <div className="flex flex-1 items-center gap-2 font-medium">
                    <Crest team={game.away} />
                    {game.away ? <Link href={game.away.url} className="hover:underline">{game.away.name}</Link> : 'A definir'}
                </div>
            </div>
            <div className="mt-1 flex flex-wrap justify-center gap-x-3 text-xs text-gray-500">
                {game.live && <span className="font-semibold text-red-600">● AO VIVO</span>}
                {!game.live && !game.decided && <span>{formatWhen(game.scheduled_at)}</span>}
                {game.decided && game.home_penalties !== null && game.away_penalties !== null && <span>({game.home_penalties} x {game.away_penalties} nos pênaltis)</span>}
                {game.leg && game.leg > 0 && game.status !== 'finished' && <span>{game.leg === 1 ? 'Ida' : 'Volta'}</span>}
                {game.group && <span>{game.group}</span>}
                {game.venue && <span>{game.venue}</span>}
                {game.competition && <span>{game.competition}</span>}
                {!game.live && !game.decided && game.status !== 'scheduled' && <span>{game.status_label}</span>}
            </div>
        </li>
    );
}

export function GameList({ games, empty }: { games: PortalGame[]; empty?: string }) {
    if (games.length === 0) {
        return <p className="py-4 text-sm text-gray-500">{empty ?? 'Nenhum jogo.'}</p>;
    }

    return <ul className="divide-y divide-gray-100">{games.map((game) => <GameRow key={game.id} game={game} />)}</ul>;
}
